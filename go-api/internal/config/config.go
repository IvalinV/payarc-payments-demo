package config

import (
	"fmt"
	"os"
	"strconv"
	"time"
)

type Config struct {
	Addr, DBDSN, JWTSecret, SpatieModelType string
	ReadTimeout, WriteTimeout, IdleTimeout  time.Duration
	DBMaxOpenConns, DBMaxIdleConns          int
	DBConnMaxLifetime, DBConnMaxIdleTime    time.Duration
}

func Load() (Config, error) {
	c := Config{
		Addr:              env("API_ADDR", ":8081"),
		DBDSN:             os.Getenv("DB_DSN"),
		JWTSecret:         os.Getenv("JWT_SECRET"),
		SpatieModelType:   env("SPATIE_USER_MODEL_TYPE", "App\\Containers\\AppSection\\User\\Models\\User"),
		ReadTimeout:       10 * time.Second,
		WriteTimeout:      15 * time.Second,
		IdleTimeout:       60 * time.Second,
		DBMaxOpenConns:    intEnv("DB_MAX_OPEN_CONNS", 25),
		DBMaxIdleConns:    intEnv("DB_MAX_IDLE_CONNS", 10),
		DBConnMaxLifetime: durationEnv("DB_CONN_MAX_LIFETIME", 5*time.Minute),
		DBConnMaxIdleTime: durationEnv("DB_CONN_MAX_IDLE_TIME", time.Minute),
	}
	if c.DBDSN == "" {
		c.DBDSN = fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?parseTime=true&loc=UTC", env("DB_USERNAME", "payarc"), env("DB_PASSWORD", "payarc"), env("DB_HOST", "db"), env("DB_PORT", "3306"), env("DB_DATABASE", "payarc"))
	}
	if c.JWTSecret == "" {
		return Config{}, fmt.Errorf("JWT_SECRET is required")
	}
	return c, nil
}

func env(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return fallback
}
func intEnv(key string, fallback int) int {
	value, err := strconv.Atoi(os.Getenv(key))
	if err != nil {
		return fallback
	}
	return value
}

func durationEnv(key string, fallback time.Duration) time.Duration {
	value, err := time.ParseDuration(os.Getenv(key))
	if err != nil || value <= 0 {
		return fallback
	}
	return value
}
