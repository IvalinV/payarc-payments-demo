FROM golang:1.22-alpine AS build

WORKDIR /src

COPY go-api/go.mod ./go.mod
COPY go-api/go.sum ./go.sum
RUN go mod download
COPY go-api/cmd ./cmd
COPY go-api/internal ./internal

RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /out/payarc-api ./cmd/api

FROM alpine:3.20

RUN apk add --no-cache wget

COPY --from=build /out/payarc-api /usr/local/bin/payarc-api

EXPOSE 8081

ENTRYPOINT ["/usr/local/bin/payarc-api"]
