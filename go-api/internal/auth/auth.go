package auth

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"errors"
	"fmt"
	"strings"
	"time"

	"golang.org/x/crypto/bcrypt"
)

var ErrInvalidToken = errors.New("invalid token")

type Claims struct {
	Subject             string
	IssuedAt, ExpiresAt time.Time
}
type tokenHeader struct {
	Alg string `json:"alg"`
	Typ string `json:"typ"`
}
type tokenClaims struct {
	Subject   string `json:"sub"`
	IssuedAt  int64  `json:"iat"`
	ExpiresAt int64  `json:"exp"`
}

func CheckPassword(hash, password string) bool {
	return bcrypt.CompareHashAndPassword([]byte(hash), []byte(password)) == nil
}

func Issue(subject, secret string, now time.Time) (string, error) {
	if secret == "" {
		return "", errors.New("empty jwt secret")
	}
	encode := func(value any) (string, error) {
		data, err := json.Marshal(value)
		return base64.RawURLEncoding.EncodeToString(data), err
	}
	h, _ := encode(tokenHeader{Alg: "HS256", Typ: "JWT"})
	c, err := encode(tokenClaims{Subject: subject, IssuedAt: now.Unix(), ExpiresAt: now.Add(time.Hour).Unix()})
	if err != nil {
		return "", err
	}
	signature := sign(h+"."+c, secret)
	return h + "." + c + "." + signature, nil
}

func Parse(token, secret string, now time.Time) (Claims, error) {
	parts := strings.Split(token, ".")
	if len(parts) != 3 || secret == "" || !hmac.Equal([]byte(sign(parts[0]+"."+parts[1], secret)), []byte(parts[2])) {
		return Claims{}, ErrInvalidToken
	}
	var header tokenHeader
	var payload tokenClaims
	decode := func(value string, target any) error {
		data, err := base64.RawURLEncoding.DecodeString(value)
		if err != nil {
			return err
		}
		return json.Unmarshal(data, target)
	}
	if decode(parts[0], &header) != nil || header.Alg != "HS256" || decode(parts[1], &payload) != nil || payload.Subject == "" || payload.IssuedAt <= 0 || payload.ExpiresAt <= payload.IssuedAt || now.Unix() >= payload.ExpiresAt {
		return Claims{}, ErrInvalidToken
	}
	return Claims{Subject: payload.Subject, IssuedAt: time.Unix(payload.IssuedAt, 0), ExpiresAt: time.Unix(payload.ExpiresAt, 0)}, nil
}

func sign(value, secret string) string {
	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write([]byte(value))
	return base64.RawURLEncoding.EncodeToString(mac.Sum(nil))
}
func (c Claims) String() string { return fmt.Sprintf("%s", c.Subject) }
