package config

import "testing"

func TestLoadReadsDatabasePoolSettings(t *testing.T) {
	t.Setenv("JWT_SECRET", "test-secret")
	t.Setenv("DB_MAX_OPEN_CONNS", "17")
	t.Setenv("DB_MAX_IDLE_CONNS", "7")
	t.Setenv("DB_CONN_MAX_LIFETIME", "2m")
	t.Setenv("DB_CONN_MAX_IDLE_TIME", "30s")

	c, err := Load()
	if err != nil {
		t.Fatal(err)
	}
	if c.DBMaxOpenConns != 17 || c.DBMaxIdleConns != 7 {
		t.Fatalf("unexpected pool sizes: open=%d idle=%d", c.DBMaxOpenConns, c.DBMaxIdleConns)
	}
	if c.DBConnMaxLifetime.String() != "2m0s" || c.DBConnMaxIdleTime.String() != "30s" {
		t.Fatalf("unexpected pool lifetimes: lifetime=%s idle=%s", c.DBConnMaxLifetime, c.DBConnMaxIdleTime)
	}
}
