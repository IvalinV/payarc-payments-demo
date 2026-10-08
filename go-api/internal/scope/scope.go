package scope

import (
	"context"
	"database/sql"
	"payarc-payments-demo/go-api/internal/authz"
)

type queryer interface {
	QueryContext(context.Context, string, ...any) (*sql.Rows, error)
}

func Accounts(ctx context.Context, db queryer, user authz.User) ([]string, error) {
	if hasRole(user, "admin") {
		rows, err := db.QueryContext(ctx, `SELECT id FROM accounts`)
		return scan(rows, err)
	}
	ids := make([]string, 0)
	seen := map[string]bool{}
	for _, role := range user.Roles {
		var rows *sql.Rows
		var err error
		if role == "merchant" {
			rows, err = db.QueryContext(ctx, `SELECT account_id FROM account_user WHERE user_id=?`, user.ID)
		} else if role == "agent" {
			rows, err = db.QueryContext(ctx, `SELECT aa.account_id FROM agent_account aa JOIN agent_user au ON au.agent_id=aa.agent_id WHERE au.user_id=?`, user.ID)
		} else {
			continue
		}
		if err != nil {
			return nil, err
		}
		found, err := scan(rows, nil)
		if err != nil {
			return nil, err
		}
		for _, id := range found {
			if !seen[id] {
				seen[id] = true
				ids = append(ids, id)
			}
		}
	}
	return ids, nil
}

func hasRole(user authz.User, wanted string) bool {
	for _, role := range user.Roles {
		if role == wanted {
			return true
		}
	}
	return false
}
func scan(rows *sql.Rows, err error) ([]string, error) {
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var ids []string
	for rows.Next() {
		var id string
		if err := rows.Scan(&id); err != nil {
			return nil, err
		}
		ids = append(ids, id)
	}
	return ids, rows.Err()
}
