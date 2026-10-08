package main

import (
	"context"
	"database/sql"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	_ "github.com/go-sql-driver/mysql"
	"payarc-payments-demo/go-api/internal/config"
	"payarc-payments-demo/go-api/internal/db"
	"payarc-payments-demo/go-api/internal/httpapi"
)

func main() {
	logger := slog.New(slog.NewJSONHandler(os.Stdout, nil))
	cfg, err := config.Load()
	if err != nil {
		logger.Error("config", "error", err)
		os.Exit(1)
	}
	database, err := sql.Open("mysql", cfg.DBDSN)
	if err != nil {
		logger.Error("database", "error", err)
		os.Exit(1)
	}
	defer database.Close()
	database.SetMaxOpenConns(cfg.DBMaxOpenConns)
	database.SetMaxIdleConns(cfg.DBMaxIdleConns)
	database.SetConnMaxLifetime(cfg.DBConnMaxLifetime)
	database.SetConnMaxIdleTime(cfg.DBConnMaxIdleTime)
	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()
	waitCtx, cancel := context.WithTimeout(ctx, 120*time.Second)
	defer cancel()
	if err := db.WaitForSchema(waitCtx, database); err != nil {
		logger.Error("schema readiness", "error", err)
		os.Exit(1)
	}
	httpServer := &httpapi.Server{DB: database, JWTSecret: cfg.JWTSecret, ModelType: cfg.SpatieModelType, Logger: logger, LoginLimiter: httpapi.NewLoginLimiter(5, time.Minute)}
	server := &http.Server{Addr: cfg.Addr, Handler: httpServer.Routes(), ReadHeaderTimeout: 5 * time.Second, ReadTimeout: cfg.ReadTimeout, WriteTimeout: cfg.WriteTimeout, IdleTimeout: cfg.IdleTimeout}
	go func() {
		logger.Info("api listening", "addr", cfg.Addr)
		if err := server.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			logger.Error("server", "error", err)
			stop()
		}
	}()
	<-ctx.Done()
	shutdown, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	_ = server.Shutdown(shutdown)
}
