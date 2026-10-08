package httpapi

import "testing"

func TestParseChargeQueryWhitelistsSortAndBoundsPageSize(t *testing.T) {
	q, err := parseChargeQuery("page=2&page_size=500&sort=created_at:desc&status=captured&amount_min=10")
	if err != nil || q.Page != 2 || q.PageSize != 100 || q.Sort != "created_at DESC" {
		t.Fatalf("query = %#v, %v", q, err)
	}
	if q.Filters["status"] != "captured" || q.Filters["amount_min"] != "10" {
		t.Fatalf("filters = %#v", q.Filters)
	}
}

func TestParseChargeQueryRejectsUnknownSort(t *testing.T) {
	if _, err := parseChargeQuery("sort=customer_email:sideways"); err == nil {
		t.Fatal("expected invalid sort error")
	}
}

func TestParseChargeQueryRejectsInvalidFilterValues(t *testing.T) {
	tests := []struct {
		name  string
		query string
	}{
		{name: "unknown status", query: "status=pending"},
		{name: "unknown currency", query: "currency=CAD"},
		{name: "invalid minimum amount", query: "amount_min=not-a-number"},
		{name: "invalid date", query: "created_from=not-a-date"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if _, err := parseChargeQuery(tt.query); err == nil {
				t.Fatal("expected invalid filter error")
			}
		})
	}
}

func TestParseChargeQueryRejectsOffsetOverflow(t *testing.T) {
	if _, err := parseChargeQuery("page=9223372036854775807&page_size=100"); err == nil {
		t.Fatal("expected offset overflow error")
	}
}

func TestParseChargeQueryNormalizesCurrency(t *testing.T) {
	q, err := parseChargeQuery("currency=usd")
	if err != nil {
		t.Fatal(err)
	}
	if q.Filters["currency"] != "USD" {
		t.Fatalf("currency filter = %q", q.Filters["currency"])
	}
}

func TestParseChargeQueryNormalizesDateToUTC(t *testing.T) {
	q, err := parseChargeQuery("created_from=2026-01-01T03:00:00%2B03:00")
	if err != nil {
		t.Fatal(err)
	}
	if q.Filters["created_from"] != "2026-01-01 00:00:00" {
		t.Fatalf("date filter = %q", q.Filters["created_from"])
	}
}
