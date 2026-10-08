package httpapi

import (
	"fmt"
	"net/url"
	"strconv"
	"strings"
	"time"
)

type chargeQuery struct {
	Page, PageSize int
	Sort           string
	Filters        map[string]string
}

var sortFields = map[string]string{
	"created_at":              "charges.created_at",
	"external_transaction_id": "charges.external_transaction_id",
	"merchant":                "accounts.business_name",
	"customer_email":          "charges.customer_email",
	"amount":                  "charges.amount",
	"amount_captured":         "charges.amount_captured",
	"amount_refunded":         "charges.amount_refunded",
	"status":                  "charges.status",
}

func parseChargeQuery(raw string) (chargeQuery, error) {
	values, err := url.ParseQuery(raw)
	if err != nil {
		return chargeQuery{}, err
	}
	q := chargeQuery{Page: 1, PageSize: 25, Sort: "charges.created_at DESC", Filters: map[string]string{}}
	if v := values.Get("page"); v != "" {
		q.Page, err = strconv.Atoi(v)
		if err != nil || q.Page < 1 {
			return q, fmt.Errorf("invalid page")
		}
	}
	if v := values.Get("page_size"); v != "" {
		q.PageSize, err = strconv.Atoi(v)
		if err != nil || q.PageSize < 1 {
			return q, fmt.Errorf("invalid page_size")
		}
		if q.PageSize > 100 {
			q.PageSize = 100
		}
	}
	maxInt := int(^uint(0) >> 1)
	if q.Page > maxInt/q.PageSize {
		return q, fmt.Errorf("invalid page")
	}
	if v := values.Get("sort"); v != "" {
		var sorts []string
		for _, sort := range strings.Split(v, ",") {
			parts := strings.Split(sort, ":")
			if len(parts) != 2 || (parts[1] != "asc" && parts[1] != "desc") || sortFields[parts[0]] == "" {
				return q, fmt.Errorf("invalid sort")
			}
			sorts = append(sorts, sortFields[parts[0]]+" "+strings.ToUpper(parts[1]))
		}
		q.Sort = strings.Join(sorts, ", ")
	}
	for _, key := range []string{"status", "currency", "account_id", "q", "created_from", "created_to", "amount_min", "amount_max"} {
		if v := values.Get(key); v != "" {
			if key == "currency" {
				v = strings.ToUpper(v)
			}
			if err := validateFilter(key, v); err != nil {
				return q, err
			}
			if key == "created_from" || key == "created_to" {
				parsed, _ := parseDate(v)
				v = parsed.UTC().Format("2006-01-02 15:04:05")
			}
			q.Filters[key] = v
		}
	}
	if min, max := q.Filters["amount_min"], q.Filters["amount_max"]; min != "" && max != "" {
		minValue, _ := strconv.ParseInt(min, 10, 64)
		maxValue, _ := strconv.ParseInt(max, 10, 64)
		if minValue > maxValue {
			return q, fmt.Errorf("amount_min must not exceed amount_max")
		}
	}
	return q, nil
}

func validateFilter(key, value string) error {
	switch key {
	case "status":
		if !map[string]bool{"authorized": true, "captured": true, "refunded": true, "voided": true, "failed": true}[value] {
			return fmt.Errorf("invalid status")
		}
	case "currency":
		if !map[string]bool{"USD": true, "EUR": true, "GBP": true, "BGN": true}[strings.ToUpper(value)] {
			return fmt.Errorf("invalid currency")
		}
	case "amount_min", "amount_max":
		amount, err := strconv.ParseInt(value, 10, 64)
		if err != nil || amount < 0 {
			return fmt.Errorf("invalid %s", key)
		}
	case "created_from", "created_to":
		if _, err := parseDate(value); err != nil {
			return fmt.Errorf("invalid %s", key)
		}
	}
	return nil
}

func parseDate(value string) (time.Time, error) {
	if parsed, err := time.Parse(time.RFC3339, value); err == nil {
		return parsed, nil
	}
	return time.Parse("2006-01-02", value)
}
