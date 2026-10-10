BASE_URL ?= http://localhost:8080

.PHONY: test test-php test-go test-web smoke

test-php:
	docker compose exec -T php sh -c 'created_env=0; if [ ! -f .env ]; then cp ../.env .env; created_env=1; fi; trap "if [ \"$$created_env\" = 1 ]; then rm -f .env; fi" EXIT; php artisan test'

test-go:
	docker run --rm \
		--mount type=bind,src="$(CURDIR)",dst=/workspace,readonly \
		-w /workspace/go-api \
		golang:1.22-alpine \
		go test ./...

test-web:
	docker run --rm \
		--mount type=bind,src="$(CURDIR)/web",dst=/app,readonly \
		-w /app \
		node:22-alpine \
		sh -c 'cp -R /app /tmp/web && cd /tmp/web && npm ci && npm test -- --run && npm run typecheck'

smoke:
	BASE_URL=$(BASE_URL) ./scripts/smoke.sh

test: test-php test-go test-web
