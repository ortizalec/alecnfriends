# Alec & Friends - Local Development Makefile

.PHONY: help install backend frontend dev clean db-reset

# Load environment variables from .env
ifneq (,$(wildcard ./.env))
	include .env
	export
endif

help:
	@echo "Available targets:"
	@echo "  make install   - Install all dependencies (backend + frontend)"
	@echo "  make backend   - Run the backend server (port 8080)"
	@echo "  make frontend  - Run the frontend dev server (port 5173)"
	@echo "  make dev       - Run both backend and frontend (requires two terminals)"
	@echo "  make clean     - Remove build artifacts and node_modules"
	@echo "  make db-reset  - Delete the local SQLite database"

# Install all dependencies
install: install-backend install-frontend

install-backend:
	@echo "Installing backend dependencies..."
	cd backend && go mod download

install-frontend:
	@echo "Installing frontend dependencies..."
	cd frontend && npm install

# Run backend server
backend:
	@echo "Starting backend server on http://localhost:8080..."
	@mkdir -p backend/data
	cd backend && go run cmd/server/main.go

# Run frontend dev server
frontend:
	@echo "Starting frontend dev server on http://localhost:5173..."
	cd frontend && npm run dev

# Development helper - prints instructions for running both
dev:
	@echo "To run the full stack locally, open two terminals:"
	@echo ""
	@echo "  Terminal 1: make backend"
	@echo "  Terminal 2: make frontend"
	@echo ""
	@echo "Then open http://localhost:5173 in your browser."
	@echo ""
	@echo "The frontend will proxy API requests to the backend automatically."

# Clean build artifacts
clean:
	@echo "Cleaning build artifacts..."
	rm -rf frontend/node_modules
	rm -rf frontend/dist
	rm -rf backend/data

# Reset the database
db-reset:
	@echo "Deleting local database..."
	rm -f backend/data/alecnfriends.db
	@echo "Database deleted. It will be recreated on next backend start."
