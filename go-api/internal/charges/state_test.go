package charges

import (
	"encoding/json"
	"errors"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestStateMachineUsesSharedContract(t *testing.T) {
	path := filepath.Join("..", "..", "..", "contracts", "charge_rules.json")
	data, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	var contract struct {
		Currencies []string `json:"currencies"`
		Amount     struct {
			Minimum int64 `json:"minimum"`
			Maximum int64 `json:"maximum"`
		} `json:"amount"`
	}
	if err := json.Unmarshal(data, &contract); err != nil {
		t.Fatal(err)
	}
	for _, currency := range contract.Currencies {
		if _, err := Create(contract.Amount.Minimum, currency, true); err != nil {
			t.Errorf("contract currency %s rejected: %v", currency, err)
		}
	}
	if _, err := Create(contract.Amount.Minimum-1, contract.Currencies[0], true); err == nil {
		t.Error("contract minimum-1 amount was accepted")
	}
	if _, err := Create(contract.Amount.Maximum+1, contract.Currencies[0], true); err == nil {
		t.Error("contract maximum+1 amount was accepted")
	}
}

func TestStateMachineTransitionsFromSharedContract(t *testing.T) {
	path := filepath.Join("..", "..", "..", "contracts", "charge_rules.json")
	data, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	var contract struct {
		Operations map[string]struct {
			From []string `json:"from"`
			To   string   `json:"to"`
		} `json:"operations"`
	}
	if err := json.Unmarshal(data, &contract); err != nil {
		t.Fatal(err)
	}

	capture := Charge{Amount: 1000, Status: StatusAuthorized}
	if err := capture.Capture(); err != nil || capture.Status != contract.Operations["capture"].To || capture.AmountCaptured != capture.Amount {
		t.Fatalf("capture transition = %#v, %v", capture, err)
	}

	refund := Charge{Amount: 1000, AmountCaptured: 1000, Status: StatusCaptured}
	if err := refund.RefundAmount(400, false); err != nil || refund.AmountRefunded != 400 || !refund.Refunded {
		t.Fatalf("partial refund transition = %#v, %v", refund, err)
	}
	if err := refund.RefundAmount(600, false); err != nil || refund.Status != "refunded" {
		t.Fatalf("full refund transition = %#v, %v", refund, err)
	}

	void := Charge{Amount: 1000, Status: StatusAuthorized}
	if err := void.Void("customer request"); err != nil || void.Status != contract.Operations["void"].To || void.AmountVoided != void.Amount {
		t.Fatalf("void transition = %#v, %v", void, err)
	}
}

func TestCreateReturnsRandomnessError(t *testing.T) {
	original := randomRead
	randomRead = func([]byte) (int, error) { return 0, errors.New("random source unavailable") }
	t.Cleanup(func() { randomRead = original })

	if _, err := Create(100, "USD", true); err == nil {
		t.Fatal("expected randomness error")
	}
}

func TestCreateGeneratesULIDTransactionID(t *testing.T) {
	c, err := Create(100, "USD", true)
	if err != nil {
		t.Fatal(err)
	}
	if !strings.HasPrefix(c.ExternalTransactionID, "txn_") || len(c.ExternalTransactionID) != len("txn_")+26 {
		t.Fatalf("transaction ID = %q, want txn_ followed by 26 ULID characters", c.ExternalTransactionID)
	}
}

func TestCreateCaptureDefaults(t *testing.T) {
	c, err := Create(1000, "USD", true)
	if err != nil || c.Status != StatusCaptured || c.AmountCaptured != 1000 {
		t.Fatalf("create capture = %#v, %v", c, err)
	}
}

func TestRefundTransitionsAndRejectsOverRefund(t *testing.T) {
	c := Charge{Amount: 1000, AmountCaptured: 1000, Status: StatusCaptured}
	if err := c.Refund(400); err != nil || c.AmountRefunded != 400 {
		t.Fatalf("partial refund = %#v, %v", c, err)
	}
	err := c.Refund(601)
	if e, ok := err.(*Error); !ok || e.Code != "amount_exceeds_refundable" {
		t.Fatalf("expected excess refund error, got %v", err)
	}
	if err := c.Refund(600); err != nil || c.Status != StatusRefunded {
		t.Fatalf("full refund = %#v, %v", c, err)
	}
}

func TestRefundAmountRejectsExplicitZero(t *testing.T) {
	c := Charge{Amount: 1000, AmountCaptured: 1000, Status: StatusCaptured}
	if err := c.RefundAmount(0, false); err == nil {
		t.Fatal("expected explicit zero refund to be rejected")
	}
}

func TestVoidOnlyAuthorized(t *testing.T) {
	c := Charge{Amount: 1000, Status: StatusAuthorized}
	if err := c.Void("customer request"); err != nil || c.Status != StatusVoided || c.AmountVoided != 1000 {
		t.Fatalf("void = %#v, %v", c, err)
	}
	err := c.Void("again")
	if e, ok := err.(*Error); !ok || e.Code != "charge_already_voided" {
		t.Fatalf("expected already voided, got %v", err)
	}
}

func TestCreateAcceptsLowercaseCurrency(t *testing.T) {
	c, err := Create(1000, "usd", true)
	if err != nil || c.Currency != "USD" {
		t.Fatalf("lowercase currency = %#v, %v", c, err)
	}
}

func TestVoidRequiresReason(t *testing.T) {
	c := Charge{Amount: 1000, Status: StatusAuthorized}
	err := c.Void("  ")
	if e, ok := err.(*Error); !ok || e.Code != "invalid_reason" || e.Status != 422 {
		t.Fatalf("expected invalid reason, got %v", err)
	}
}
