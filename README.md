# Payarc Payments Demo

Laravel/Nova admin portal, Go payments API, and React merchant portal running
behind one Docker Compose stack.

## Setup

Requirements:

- Docker and Docker Compose
- Nova credentials configured as `NOVA_USERNAME` and `NOVA_LICENSE_KEY`

Start the application:

```bash
docker compose up --build
```

The application is available at:

- Merchant portal: `http://localhost:8080/`
- Nova admin: `http://localhost:8080/admin`
- API health: `http://localhost:8080/healthz`

The stack runs MySQL, PHP/Laravel, the Go API, and nginx/React. Database
migrations and seeders run from the PHP service during startup.

## Demo Accounts

All seeded users use the password `Demo#12345`.

| Email | Role | Scope |
|---|---|---|
| `admin@payarc.test` | admin | All accounts; Nova access |
| `agent1@payarc.test` | agent | Assigned accounts |
| `agent2@payarc.test` | agent | Assigned accounts |
| `merchant1@payarc.test` | merchant | One account |
| `merchant2@payarc.test` | merchant | Two accounts |
| `merchant3@payarc.test` | merchant | One account |
| `norole@payarc.test` | none | No portal/API access |

Permissions are managed by Spatie:
`admin.access`, `charges.view`, `charges.create`, `charges.refund`,
`charges.void`, `merchants.manage`, `agents.manage`, and `users.manage`.
The Go API reloads permissions and account scope on every request.

## API

Login:

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"merchant1@payarc.test","password":"Demo#12345"}'
```

The API supports authenticated account and charge queries, charge creation,
capture, refund, and void operations. Use the returned bearer token for
authenticated requests.

## Tests

```bash
npm test
npm run build
cd go-api && go test ./...
```

Run MySQL integration checks by setting `GO_API_INTEGRATION_DSN` before the Go
test command.
