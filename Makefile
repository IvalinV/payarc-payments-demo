BASE_URL ?= http://localhost:8080

.PHONY: test test-php test-go test-web smoke

test-php:
	docker compose exec -T db sh -c 'mysql -uroot -p"$$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS payarc_test; GRANT ALL PRIVILEGES ON payarc_test.* TO '\''$$MYSQL_USER'\''@'\''%'\''"'
	docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=payarc_test php sh -c 'php artisan test'

test-go:
	docker compose run --rm go-test

test-web:
	docker compose run --rm web-test

smoke:
	BASE_URL=$(BASE_URL) ./scripts/smoke.sh

test: test-php test-go test-web
