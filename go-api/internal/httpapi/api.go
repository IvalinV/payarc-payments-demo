package httpapi

import (
	"bytes"
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"io"
	"log/slog"
	"net"
	"net/http"
	"net/mail"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/go-sql-driver/mysql"
	"payarc-payments-demo/go-api/internal/auth"
	"payarc-payments-demo/go-api/internal/authz"
	"payarc-payments-demo/go-api/internal/charges"
	"payarc-payments-demo/go-api/internal/db"
	"payarc-payments-demo/go-api/internal/scope"
)

type Server struct {
	DB                   *sql.DB
	JWTSecret, ModelType string
	Logger               *slog.Logger
	LoginLimiter         *LoginLimiter
}

type refundInput struct {
	Amount *int64 `json:"amount"`
	Reason string `json:"reason"`
}

type loginAttempt struct {
	started time.Time
	count   int
}

type LoginLimiter struct {
	mu       sync.Mutex
	limit    int
	window   time.Duration
	attempts map[string]loginAttempt
}

func NewLoginLimiter(limit int, window time.Duration) *LoginLimiter {
	return &LoginLimiter{limit: limit, window: window, attempts: make(map[string]loginAttempt)}
}

func (l *LoginLimiter) Allow(key string, now time.Time) bool {
	l.mu.Lock()
	defer l.mu.Unlock()
	entry := l.attempts[key]
	if entry.started.IsZero() || now.Sub(entry.started) >= l.window {
		l.attempts[key] = loginAttempt{started: now, count: 1}
		return true
	}
	if entry.count >= l.limit {
		return false
	}
	entry.count++
	l.attempts[key] = entry
	return true
}

func (l *LoginLimiter) Reset(key string) {
	l.mu.Lock()
	defer l.mu.Unlock()
	delete(l.attempts, key)
}

type ctxKey string

const userKey ctxKey = "user"

func (s *Server) Routes() http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("GET /healthz", s.health)
	mux.HandleFunc("POST /api/auth/login", s.login)
	mux.Handle("GET /api/me", s.authn(http.HandlerFunc(s.me)))
	mux.Handle("GET /api/accounts", s.permission("charges.view", http.HandlerFunc(s.accounts)))
	mux.Handle("GET /api/charges", s.permission("charges.view", http.HandlerFunc(s.listCharges)))
	mux.Handle("GET /api/charges/{id}", s.permission("charges.view", http.HandlerFunc(s.getCharge)))
	mux.Handle("POST /api/charges", s.permission("charges.create", http.HandlerFunc(s.createCharge)))
	mux.Handle("POST /api/charges/{id}/capture", s.permission("charges.create", http.HandlerFunc(s.capture)))
	mux.Handle("POST /api/charges/{id}/refund", s.permission("charges.refund", http.HandlerFunc(s.refund)))
	mux.Handle("POST /api/charges/{id}/void", s.permission("charges.void", http.HandlerFunc(s.void)))
	return s.recover(s.requestID(s.logging(mux)))
}
func (s *Server) health(w http.ResponseWriter, r *http.Request) {
	if err := db.Check(r.Context(), s.DB); err != nil {
		writeError(w, 503, "unhealthy", "service unavailable")
		return
	}
	writeJSON(w, 200, map[string]string{"status": "ok"})
}
func (s *Server) login(w http.ResponseWriter, r *http.Request) {
	key := clientIP(r)
	if s.LoginLimiter != nil && !s.LoginLimiter.Allow(key, time.Now()) {
		writeError(w, http.StatusTooManyRequests, "rate_limited", "too many login attempts")
		return
	}
	var input struct{ Email, Password string }
	if !decode(r, &input) {
		writeError(w, 422, "invalid_json", "invalid request")
		return
	}
	var id, hash string
	err := s.DB.QueryRowContext(r.Context(), `SELECT id,password FROM users WHERE email=?`, input.Email).Scan(&id, &hash)
	if errors.Is(err, sql.ErrNoRows) || (err == nil && !auth.CheckPassword(hash, input.Password)) {
		writeError(w, 401, "invalid_credentials", "invalid credentials")
		return
	}
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	if s.LoginLimiter != nil {
		s.LoginLimiter.Reset(key)
	}
	token, err := auth.Issue(id, s.JWTSecret, time.Now())
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	writeJSON(w, 200, map[string]string{"token": token})
}
func (s *Server) me(w http.ResponseWriter, r *http.Request) {
	u := r.Context().Value(userKey).(authz.User)
	accounts, err := scope.Accounts(r.Context(), s.DB, u)
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	writeJSON(w, 200, map[string]any{"data": map[string]any{"id": u.ID, "email": u.Email, "roles": u.Roles, "permissions": u.Permissions, "account_ids": accounts}})
}
func (s *Server) accounts(w http.ResponseWriter, r *http.Request) {
	u := r.Context().Value(userKey).(authz.User)
	ids, err := scope.Accounts(r.Context(), s.DB, u)
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	if len(ids) == 0 {
		writeJSON(w, 200, map[string]any{"data": []any{}, "meta": map[string]int{"page": 1, "page_size": 25, "total": 0}})
		return
	}
	placeholders := strings.TrimRight(strings.Repeat("?,", len(ids)), ",")
	args := make([]any, len(ids))
	for i, id := range ids {
		args[i] = id
	}
	rows, err := s.DB.QueryContext(r.Context(), `SELECT id,business_name,dba_name,mid,status FROM accounts WHERE id IN (`+placeholders+`)`, args...)
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	defer rows.Close()
	var data []map[string]any
	for rows.Next() {
		var id, business, dba, mid, status string
		if err := rows.Scan(&id, &business, &dba, &mid, &status); err != nil {
			writeError(w, 500, "internal_error", "internal server error")
			return
		}
		data = append(data, map[string]any{"id": id, "business_name": business, "dba_name": dba, "mid": mid, "status": status})
	}
	if err := rows.Err(); err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	writeJSON(w, 200, map[string]any{"data": data, "meta": map[string]int{"page": 1, "page_size": 25, "total": len(data)}})
}
func (s *Server) listCharges(w http.ResponseWriter, r *http.Request) {
	q, err := parseChargeQuery(r.URL.RawQuery)
	if err != nil {
		writeError(w, 422, "invalid_filter", err.Error())
		return
	}
	u := r.Context().Value(userKey).(authz.User)
	ids, err := scope.Accounts(r.Context(), s.DB, u)
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	if len(ids) == 0 {
		writeJSON(w, 200, map[string]any{"data": []any{}, "meta": map[string]int{"page": q.Page, "page_size": q.PageSize, "total": 0}})
		return
	}
	ph := strings.TrimRight(strings.Repeat("?,", len(ids)), ",")
	args := make([]any, len(ids))
	for i, id := range ids {
		args[i] = id
	}
	where := `account_id IN (` + ph + `)`
	for key, value := range q.Filters {
		switch key {
		case "status", "currency", "account_id":
			where += " AND " + key + "=?"
			args = append(args, value)
		case "q":
			where += " AND (customer_email LIKE ? OR external_transaction_id LIKE ?)"
			args = append(args, "%"+value+"%", "%"+value+"%")
		case "amount_min", "amount_max":
			op := ">="
			if key == "amount_max" {
				op = "<="
			}
			where += " AND amount " + op + " ?"
			args = append(args, value)
		case "created_from", "created_to":
			op := ">="
			if key == "created_to" {
				op = "<="
			}
			where += " AND created_at " + op + " ?"
			args = append(args, value)
		}
	}
	var total int
	countArgs := append([]any{}, args...)
	if err = s.DB.QueryRowContext(r.Context(), `SELECT COUNT(*) FROM charges WHERE `+where, countArgs...).Scan(&total); err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	args = append(args, q.PageSize, (q.Page-1)*q.PageSize)
	rows, err := s.DB.QueryContext(r.Context(), `SELECT id,account_id,amount,amount_captured,amount_refunded,amount_voided,currency,status,customer_email,external_transaction_id,refunded,disputed,description,void_reason,created_at FROM charges WHERE `+where+` ORDER BY `+q.Sort+` LIMIT ? OFFSET ?`, args...)
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	defer rows.Close()
	data := []map[string]any{}
	for rows.Next() {
		var id, account, cur, status, email, txn string
		var description, voidReason sql.NullString
		var amount, captured, refunded, voided int64
		var refundedFlag, disputed bool
		var created time.Time
		if err := rows.Scan(&id, &account, &amount, &captured, &refunded, &voided, &cur, &status, &email, &txn, &refundedFlag, &disputed, &description, &voidReason, &created); err != nil {
			writeError(w, 500, "internal_error", "internal server error")
			return
		}
		data = append(data, map[string]any{"id": id, "account_id": account, "amount": amount, "amount_captured": captured, "amount_refunded": refunded, "amount_voided": voided, "currency": cur, "status": status, "customer_email": email, "external_transaction_id": txn, "refunded": refundedFlag, "disputed": disputed, "description": nullableValue(description), "void_reason": nullableValue(voidReason), "created_at": created})
	}
	if err := rows.Err(); err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	writeJSON(w, 200, map[string]any{"data": data, "meta": map[string]int{"page": q.Page, "page_size": q.PageSize, "total": total}})
}
func (s *Server) getCharge(w http.ResponseWriter, r *http.Request) {
	u := r.Context().Value(userKey).(authz.User)
	ids, err := scope.Accounts(r.Context(), s.DB, u)
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	if len(ids) == 0 {
		writeError(w, 404, "not_found", "charge not found")
		return
	}
	var c charges.Charge
	var description, voidReason sql.NullString
	placeholders := strings.TrimRight(strings.Repeat("?,", len(ids)), ",")
	err = s.DB.QueryRowContext(r.Context(), `SELECT id,account_id,amount,amount_captured,amount_refunded,amount_voided,currency,status,customer_email,external_transaction_id,refunded,disputed,description,void_reason FROM charges WHERE id=? AND account_id IN (`+placeholders+`)`, append([]any{r.PathValue("id")}, stringArgs(ids)...)...).Scan(&c.ID, &c.AccountID, &c.Amount, &c.AmountCaptured, &c.AmountRefunded, &c.AmountVoided, &c.Currency, &c.Status, &c.CustomerEmail, &c.ExternalTransactionID, &c.Refunded, &c.Disputed, &description, &voidReason)
	if errors.Is(err, sql.ErrNoRows) {
		writeError(w, 404, "not_found", "charge not found")
		return
	}
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	c.Description, c.VoidReason = description.String, voidReason.String
	c.DescriptionValid, c.VoidReasonValid = description.Valid, voidReason.Valid
	writeJSON(w, 200, map[string]any{"data": chargeData(c)})
}
func (s *Server) createCharge(w http.ResponseWriter, r *http.Request) {
	var in struct {
		AccountID     string `json:"account_id"`
		Amount        int64  `json:"amount"`
		Currency      string `json:"currency"`
		CustomerEmail string `json:"customer_email"`
		Capture       *bool  `json:"capture"`
		Description   string `json:"description"`
	}
	if !decode(r, &in) {
		writeError(w, 422, "invalid_json", "invalid request")
		return
	}
	if err := validateCreateInput(in.CustomerEmail); err != nil {
		writeChargeError(w, err)
		return
	}
	capture := true
	if in.Capture != nil {
		capture = *in.Capture
	}
	u := r.Context().Value(userKey).(authz.User)
	ids, err := scope.Accounts(r.Context(), s.DB, u)
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	accountID := in.AccountID
	if accountID == "" && len(ids) == 1 && !u.Has("admin.access") {
		accountID = ids[0]
	}
	if accountID == "" || !contains(ids, accountID) {
		writeError(w, 404, "not_found", "account not found")
		return
	}
	c, err := charges.Create(in.Amount, in.Currency, capture)
	if err != nil {
		writeChargeError(w, err)
		return
	}
	tx, err := s.DB.BeginTx(r.Context(), nil)
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	defer tx.Rollback()
	var accountStatus string
	var active bool
	if err = tx.QueryRowContext(r.Context(), `SELECT status,active_yn FROM accounts WHERE id=? FOR UPDATE`, accountID).Scan(&accountStatus, &active); errors.Is(err, sql.ErrNoRows) {
		writeError(w, 404, "not_found", "account not found")
		return
	}
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	if accountStatus != "active" || !active {
		writeError(w, 422, "invalid_account", "the account must be active and enabled")
		return
	}
	ids, err = scope.Accounts(r.Context(), tx, u)
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	if !contains(ids, accountID) {
		writeError(w, 404, "not_found", "account not found")
		return
	}
	idempotencyKey := strings.TrimSpace(r.Header.Get("Idempotency-Key"))
	if idempotencyKey != "" {
		var existing charges.Charge
		var description, voidReason sql.NullString
		err = tx.QueryRowContext(r.Context(), `SELECT id,account_id,amount,amount_captured,amount_refunded,amount_voided,currency,status,customer_email,external_transaction_id,refunded,disputed,description,void_reason FROM charges WHERE account_id=? AND idempotency_key=?`, accountID, idempotencyKey).Scan(&existing.ID, &existing.AccountID, &existing.Amount, &existing.AmountCaptured, &existing.AmountRefunded, &existing.AmountVoided, &existing.Currency, &existing.Status, &existing.CustomerEmail, &existing.ExternalTransactionID, &existing.Refunded, &existing.Disputed, &description, &voidReason)
		if err == nil {
			existing.Description, existing.VoidReason = description.String, voidReason.String
			existing.DescriptionValid, existing.VoidReasonValid = description.Valid, voidReason.Valid
			writeJSON(w, 200, map[string]any{"data": chargeData(existing)})
			return
		}
		if !errors.Is(err, sql.ErrNoRows) {
			writeError(w, 500, "internal_error", "internal server error")
			return
		}
	}
	result, err := tx.ExecContext(r.Context(), `INSERT INTO charges (account_id,amount,amount_captured,currency,status,external_transaction_id,customer_email,description,idempotency_key,created_by,refunded,disputed,amount_refunded,amount_voided,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,false,false,0,0,UTC_TIMESTAMP(),UTC_TIMESTAMP())`, accountID, c.Amount, c.AmountCaptured, c.Currency, c.Status, c.ExternalTransactionID, in.CustomerEmail, nullIfEmpty(in.Description), nullIfEmpty(idempotencyKey), u.ID)
	if err != nil {
		if isDuplicateKeyError(err) {
			var existing charges.Charge
			var description, voidReason sql.NullString
			err = tx.QueryRowContext(r.Context(), `SELECT id,account_id,amount,amount_captured,amount_refunded,amount_voided,currency,status,customer_email,external_transaction_id,refunded,disputed,description,void_reason FROM charges WHERE account_id=? AND idempotency_key=?`, accountID, idempotencyKey).Scan(&existing.ID, &existing.AccountID, &existing.Amount, &existing.AmountCaptured, &existing.AmountRefunded, &existing.AmountVoided, &existing.Currency, &existing.Status, &existing.CustomerEmail, &existing.ExternalTransactionID, &existing.Refunded, &existing.Disputed, &description, &voidReason)
			if err == nil {
				existing.Description, existing.VoidReason = description.String, voidReason.String
				existing.DescriptionValid, existing.VoidReasonValid = description.Valid, voidReason.Valid
				writeJSON(w, 200, map[string]any{"data": chargeData(existing)})
				return
			}
			writeError(w, 409, "conflict", "charge could not be created")
			return
		}
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	if err = tx.Commit(); err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	if insertedID, idErr := result.LastInsertId(); idErr == nil {
		c.ID = strconv.FormatInt(insertedID, 10)
	}
	c.AccountID, c.CustomerEmail, c.Description = accountID, in.CustomerEmail, in.Description
	c.DescriptionValid = in.Description != ""
	writeJSON(w, 201, map[string]any{"data": chargeData(c)})
}

func nullIfEmpty(value string) any {
	if value == "" {
		return nil
	}
	return value
}
func (s *Server) capture(w http.ResponseWriter, r *http.Request) {
	s.mutate(w, r, "capture", func(c *charges.Charge) error { return c.Capture() })
}
func (s *Server) refund(w http.ResponseWriter, r *http.Request) {
	var in refundInput
	if !decode(r, &in) {
		writeError(w, 422, "invalid_json", "invalid request")
		return
	}
	s.mutateWith(w, r, "refund", func(c *charges.Charge) error {
		amount := int64(0)
		if in.Amount != nil {
			amount = *in.Amount
		}
		if err := c.RefundAmount(amount, in.Amount == nil); err != nil {
			return err
		}
		c.RefundReason = strings.TrimSpace(in.Reason)
		return nil
	})
}
func (s *Server) void(w http.ResponseWriter, r *http.Request) {
	var in struct{ Reason string }
	if !decode(r, &in) {
		writeError(w, 422, "invalid_json", "invalid request")
		return
	}
	s.mutateWith(w, r, "void", func(c *charges.Charge) error {
		if err := c.Void(in.Reason); err != nil {
			return err
		}
		c.VoidReasonValid = true
		return nil
	})
}

func clientIP(r *http.Request) string {
	if value := strings.TrimSpace(r.Header.Get("X-Real-IP")); value != "" {
		return value
	}
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err == nil {
		return host
	}
	return r.RemoteAddr
}
func (s *Server) mutate(w http.ResponseWriter, r *http.Request, op string, fn func(*charges.Charge) error) {
	s.mutateWith(w, r, op, fn)
}
func (s *Server) mutateWith(w http.ResponseWriter, r *http.Request, op string, fn func(*charges.Charge) error) {
	tx, err := s.DB.BeginTx(r.Context(), nil)
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	defer tx.Rollback()
	var c charges.Charge
	id := r.PathValue("id")
	u := r.Context().Value(userKey).(authz.User)
	ids, err := scope.Accounts(r.Context(), tx, u)
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	if len(ids) == 0 {
		writeError(w, 404, "not_found", "charge not found")
		return
	}
	placeholders := strings.TrimRight(strings.Repeat("?,", len(ids)), ",")
	var description, voidReason sql.NullString
	err = tx.QueryRowContext(r.Context(), `SELECT id,account_id,amount,amount_captured,amount_refunded,amount_voided,currency,status,customer_email,external_transaction_id,refunded,disputed,description,void_reason FROM charges WHERE id=? AND account_id IN (`+placeholders+`) FOR UPDATE`, append([]any{id}, stringArgs(ids)...)...).Scan(&c.ID, &c.AccountID, &c.Amount, &c.AmountCaptured, &c.AmountRefunded, &c.AmountVoided, &c.Currency, &c.Status, &c.CustomerEmail, &c.ExternalTransactionID, &c.Refunded, &c.Disputed, &description, &voidReason)
	if errors.Is(err, sql.ErrNoRows) {
		writeError(w, 404, "not_found", "charge not found")
		return
	}
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	c.Description, c.VoidReason = description.String, voidReason.String
	c.DescriptionValid, c.VoidReasonValid = description.Valid, voidReason.Valid
	if err = fn(&c); err != nil {
		writeChargeError(w, err)
		return
	}
	_, err = tx.ExecContext(r.Context(), `UPDATE charges SET status=?,amount_captured=?,amount_refunded=?,amount_voided=?,refunded=?,void_reason=?,updated_at=UTC_TIMESTAMP() WHERE id=?`, c.Status, c.AmountCaptured, c.AmountRefunded, c.AmountVoided, c.Refunded, nullIfEmpty(c.VoidReason), id)
	if err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	if err = tx.Commit(); err != nil {
		writeError(w, 500, "internal_error", "internal server error")
		return
	}
	writeJSON(w, 200, map[string]any{"data": chargeData(c)})
}

func stringArgs(values []string) []any {
	args := make([]any, len(values))
	for i, value := range values {
		args[i] = value
	}
	return args
}

func chargeData(c charges.Charge) map[string]any {
	return map[string]any{
		"id": c.ID, "account_id": c.AccountID, "amount": c.Amount,
		"amount_captured": c.AmountCaptured, "amount_refunded": c.AmountRefunded,
		"amount_voided": c.AmountVoided, "currency": c.Currency, "status": c.Status,
		"customer_email": c.CustomerEmail, "external_transaction_id": c.ExternalTransactionID,
		"description": nullableChargeValue(c.Description, c.DescriptionValid), "void_reason": nullableChargeValue(c.VoidReason, c.VoidReasonValid),
		"refunded": c.Refunded, "disputed": c.Disputed, "refund_reason": c.RefundReason,
	}
}

func nullableValue(value sql.NullString) any {
	if !value.Valid {
		return nil
	}
	return value.String
}

func nullableChargeValue(value string, valid bool) any {
	if !valid {
		return nil
	}
	return value
}
func contains(values []string, value string) bool {
	for _, v := range values {
		if v == value {
			return true
		}
	}
	return false
}
func decode(r *http.Request, v any) bool {
	body, err := io.ReadAll(io.LimitReader(r.Body, maxRequestBodyBytes+1))
	if err != nil || len(body) > maxRequestBodyBytes {
		return false
	}
	d := json.NewDecoder(bytes.NewReader(body))
	d.DisallowUnknownFields()
	if d.Decode(v) != nil {
		return false
	}
	var extra any
	if d.Decode(&extra) != io.EOF {
		return false
	}
	return true
}

const maxRequestBodyBytes = 1 << 20

func validateCreateInput(email string) error {
	email = strings.TrimSpace(email)
	parsed, err := mail.ParseAddress(email)
	if err != nil || parsed.Address != email || !strings.Contains(email, "@") {
		return &charges.Error{Code: "invalid_customer_email", Status: 422}
	}
	return nil
}

func isDuplicateKeyError(err error) bool {
	var mysqlErr *mysql.MySQLError
	return errors.As(err, &mysqlErr) && mysqlErr.Number == 1062
}
func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}
func writeError(w http.ResponseWriter, status int, code, message string) {
	writeJSON(w, status, map[string]any{"error": map[string]string{"code": code, "message": message}})
}
func writeChargeError(w http.ResponseWriter, err error) {
	if e, ok := err.(*charges.Error); ok {
		writeError(w, e.Status, e.Code, e.Code)
		return
	}
	writeError(w, 500, "internal_error", "internal server error")
}
func (s *Server) authn(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		h := strings.TrimPrefix(r.Header.Get("Authorization"), "Bearer ")
		claims, err := auth.Parse(h, s.JWTSecret, time.Now())
		if err != nil {
			writeError(w, 401, "unauthorized", "authentication required")
			return
		}
		u, err := authz.Load(r.Context(), s.DB, claims.Subject, s.ModelType)
		if err != nil {
			writeError(w, 401, "unauthorized", "authentication required")
			return
		}
		next.ServeHTTP(w, r.WithContext(context.WithValue(r.Context(), userKey, u)))
	})
}
func (s *Server) permission(permission string, next http.Handler) http.Handler {
	return s.authn(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if !r.Context().Value(userKey).(authz.User).Has(permission) {
			writeError(w, 403, "forbidden", "permission denied")
			return
		}
		next.ServeHTTP(w, r)
	}))
}
func (s *Server) requestID(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("X-Request-ID", strconv.FormatInt(time.Now().UnixNano(), 10))
		next.ServeHTTP(w, r)
	})
}
func (s *Server) logging(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		s.Logger.Info("request", "method", r.Method, "path", r.URL.Path)
		next.ServeHTTP(w, r)
	})
}
func (s *Server) recover(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if recover() != nil {
				writeError(w, 500, "internal_error", "internal server error")
			}
		}()
		next.ServeHTTP(w, r)
	})
}
