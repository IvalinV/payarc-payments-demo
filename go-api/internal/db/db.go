package db

import (
	"context"
	"database/sql"
	"fmt"
	"time"
)

func WaitForSchema(ctx context.Context, database *sql.DB) error {
	ticker := time.NewTicker(time.Second)
	defer ticker.Stop()
	for {
		if err := schemaReady(ctx, database); err == nil {
			return nil
		}
		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-ticker.C:
		}
	}
}

func schemaReady(ctx context.Context, database *sql.DB) error {
	var n int
	if err := database.QueryRowContext(ctx, `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('users','accounts','charges','roles','permissions','model_has_roles','role_has_permissions','model_has_permissions','account_user','agent_user','agent_account')`).Scan(&n); err != nil {
		return err
	}
	if n != 11 {
		return fmt.Errorf("required schema incomplete: %d/11 tables", n)
	}
	return nil
}

func Check(ctx context.Context, database *sql.DB) error {
	if err := database.PingContext(ctx); err != nil {
		return err
	}
	if err := schemaReady(ctx, database); err != nil {
		return fmt.Errorf("schema check: %w", err)
	}
	return nil
}
