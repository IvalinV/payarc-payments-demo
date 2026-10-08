package integration

import (
	"context"
	"database/sql"
	"os"
	"testing"
	"time"

	_ "github.com/go-sql-driver/mysql"
	"payarc-payments-demo/go-api/internal/db"
)

func TestMySQLSchemaReadiness(t *testing.T) {
	dsn := os.Getenv("GO_API_INTEGRATION_DSN")
	if dsn == "" {
		t.Skip("set GO_API_INTEGRATION_DSN to run MySQL integration tests")
	}
	database, err := sql.Open("mysql", dsn)
	if err != nil {
		t.Fatal(err)
	}
	defer database.Close()
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	if err := db.Check(ctx, database); err != nil {
		t.Fatal(err)
	}
}
