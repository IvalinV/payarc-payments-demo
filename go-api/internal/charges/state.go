package charges

import (
	"crypto/rand"
	"encoding/binary"
	"strings"
	"time"
)

var randomRead = rand.Read

const ulidAlphabet = "0123456789ABCDEFGHJKMNPQRSTVWXYZ"

const (
	StatusAuthorized = "authorized"
	StatusCaptured   = "captured"
	StatusRefunded   = "refunded"
	StatusVoided     = "voided"
	StatusFailed     = "failed"
)

type Error struct {
	Code   string
	Status int
}

func (e *Error) Error() string { return e.Code }

type Charge struct {
	ID, AccountID, Currency, CustomerEmail, Status, ExternalTransactionID, Description, VoidReason string
	Amount, AmountCaptured, AmountRefunded, AmountVoided                                           int64
	Refunded, Disputed                                                                             bool
	RefundReason                                                                                   string
	DescriptionValid, VoidReasonValid                                                              bool
}

func Create(amount int64, currency string, capture bool) (Charge, error) {
	if amount < 1 || amount > 100000000 {
		return Charge{}, &Error{"invalid_amount", 422}
	}
	currency = strings.ToUpper(currency)
	if !map[string]bool{"USD": true, "EUR": true, "GBP": true, "BGN": true}[currency] {
		return Charge{}, &Error{"invalid_currency", 422}
	}
	externalTransactionID, err := ulid()
	if err != nil {
		return Charge{}, err
	}
	c := Charge{Amount: amount, Currency: currency, ExternalTransactionID: "txn_" + externalTransactionID}
	if capture {
		c.Status, c.AmountCaptured = StatusCaptured, amount
	} else {
		c.Status = StatusAuthorized
	}
	return c, nil
}
func (c *Charge) Capture() error {
	if c.Status != StatusAuthorized {
		return &Error{"invalid_state", 409}
	}
	c.Status, c.AmountCaptured = StatusCaptured, c.Amount
	return nil
}
func (c *Charge) Refund(amount int64) error {
	return c.RefundAmount(amount, amount == 0)
}

func (c *Charge) RefundAmount(amount int64, defaultRemaining bool) error {
	if c.Status == StatusRefunded {
		return &Error{"charge_already_refunded", 409}
	}
	if c.Status != StatusCaptured || c.Disputed {
		return &Error{"invalid_state", 409}
	}
	remaining := c.AmountCaptured - c.AmountRefunded
	if defaultRemaining {
		amount = remaining
	}
	if amount <= 0 {
		return &Error{"invalid_amount", 422}
	}
	if amount > remaining {
		return &Error{"amount_exceeds_refundable", 422}
	}
	c.AmountRefunded += amount
	c.Refunded = true
	if c.AmountRefunded == c.AmountCaptured {
		c.Status = StatusRefunded
	}
	return nil
}
func (c *Charge) Void(reason string) error {
	if c.Status == StatusVoided {
		return &Error{"charge_already_voided", 409}
	}
	if c.Status != StatusAuthorized || c.Disputed {
		return &Error{"invalid_state", 409}
	}
	if strings.TrimSpace(reason) == "" {
		return &Error{"invalid_reason", 422}
	}
	c.Status, c.AmountVoided, c.VoidReason = StatusVoided, c.Amount, reason
	return nil
}
func ulid() (string, error) {
	b := make([]byte, 16)
	timestamp := time.Now().UnixMilli()
	binary.BigEndian.PutUint16(b[0:2], uint16(timestamp>>32))
	binary.BigEndian.PutUint32(b[2:6], uint32(timestamp))
	if _, err := randomRead(b[6:]); err != nil {
		return "", err
	}
	return encodeULID(b), nil
}

func encodeULID(value []byte) string {
	var encoded [26]byte
	for i := range encoded {
		var index byte
		for bit := 0; bit < 5; bit++ {
			index <<= 1
			position := i*5 + bit - 2
			if position >= 0 && value[position/8]&(1<<(7-position%8)) != 0 {
				index++
			}
		}
		encoded[i] = ulidAlphabet[index]
	}
	return string(encoded[:])
}
