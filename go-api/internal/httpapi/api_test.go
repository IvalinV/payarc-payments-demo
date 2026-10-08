package httpapi

import (
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

func TestDecodeRejectsTrailingJSON(t *testing.T) {
	req := httptest.NewRequest(http.MethodPost, "/", strings.NewReader(`{"reason":"ok"} {"reason":"extra"}`))
	var input struct {
		Reason string `json:"reason"`
	}

	if decode(req, &input) {
		t.Fatal("expected trailing JSON to be rejected")
	}
}

func TestDecodeRejectsOversizedBody(t *testing.T) {
	payload := struct {
		Reason string `json:"reason"`
	}{Reason: strings.Repeat("x", maxRequestBodyBytes)}
	body, err := json.Marshal(payload)
	if err != nil {
		t.Fatal(err)
	}
	req := httptest.NewRequest(http.MethodPost, "/", strings.NewReader(string(body)))
	var input struct {
		Reason string `json:"reason"`
	}

	if decode(req, &input) {
		t.Fatal("expected oversized body to be rejected")
	}
}

func TestDecodeRejectsOversizedTrailingData(t *testing.T) {
	body := `{"reason":"ok"}` + strings.Repeat(" ", maxRequestBodyBytes)
	req := httptest.NewRequest(http.MethodPost, "/", strings.NewReader(body))
	var input struct {
		Reason string `json:"reason"`
	}

	if decode(req, &input) {
		t.Fatal("expected body exceeding the limit to be rejected")
	}
}

func TestValidateCreateInputRejectsInvalidEmail(t *testing.T) {
	for _, email := range []string{"", "not-an-email", "Customer <customer@example.com>"} {
		if err := validateCreateInput(email); err == nil {
			t.Fatalf("expected invalid email %q to be rejected", email)
		}
	}
}

func TestValidateCreateInputAcceptsEmail(t *testing.T) {
	if err := validateCreateInput("customer@example.com"); err != nil {
		t.Fatalf("expected valid email: %v", err)
	}
}

func TestWriteChargeErrorTreatsUnknownErrorsAsInternal(t *testing.T) {
	recorder := httptest.NewRecorder()
	writeChargeError(recorder, errors.New("database unavailable"))

	if recorder.Code != http.StatusInternalServerError {
		t.Fatalf("expected status %d, got %d", http.StatusInternalServerError, recorder.Code)
	}
}

func TestRefundRejectsMalformedJSON(t *testing.T) {
	server := &Server{}
	req := httptest.NewRequest(http.MethodPost, "/api/charges/1/refund", strings.NewReader("{"))
	recorder := httptest.NewRecorder()

	server.refund(recorder, req)

	if recorder.Code != http.StatusUnprocessableEntity {
		t.Fatalf("expected status %d, got %d", http.StatusUnprocessableEntity, recorder.Code)
	}
}

func TestRefundInputAcceptsReason(t *testing.T) {
	input := refundInput{}
	req := httptest.NewRequest(http.MethodPost, "/api/charges/1/refund", strings.NewReader(`{"amount":25,"reason":"customer request"}`))
	if !decode(req, &input) {
		t.Fatal("expected refund input to be accepted")
	}
	if input.Amount == nil || *input.Amount != 25 || input.Reason != "customer request" {
		t.Fatalf("refund input = %#v", input)
	}
}

func TestRefundInputDistinguishesExplicitZero(t *testing.T) {
	input := refundInput{}
	req := httptest.NewRequest(http.MethodPost, "/api/charges/1/refund", strings.NewReader(`{"amount":0}`))
	if !decode(req, &input) || input.Amount == nil || *input.Amount != 0 {
		t.Fatalf("refund input = %#v", input)
	}
}

func TestLoginLimiterBlocksAfterThreshold(t *testing.T) {
	limiter := NewLoginLimiter(2, time.Minute)

	if !limiter.Allow("198.51.100.10", time.Now()) {
		t.Fatal("expected first attempt to be allowed")
	}
	if !limiter.Allow("198.51.100.10", time.Now()) {
		t.Fatal("expected second attempt to be allowed")
	}
	if limiter.Allow("198.51.100.10", time.Now()) {
		t.Fatal("expected third attempt to be blocked")
	}
}

func TestLoginLimiterResetsAfterWindow(t *testing.T) {
	limiter := NewLoginLimiter(1, time.Minute)
	now := time.Now()

	if !limiter.Allow("198.51.100.11", now) {
		t.Fatal("expected first attempt to be allowed")
	}
	if limiter.Allow("198.51.100.11", now.Add(30*time.Second)) {
		t.Fatal("expected attempt inside window to be blocked")
	}
	if !limiter.Allow("198.51.100.11", now.Add(time.Minute)) {
		t.Fatal("expected attempt after window to be allowed")
	}
}
