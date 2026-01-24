# Alec & Friends - Multiplayer Games

A web application for playing turn-based games with friends. Currently features Scrabble with more games planned.

## Features

- **Scrabble** - Full implementation with:
  - Canvas-rendered board with bonus squares
  - Drag-to-pan, double-tap to zoom
  - Real-time score preview
  - Blank tile support
  - Last move highlighting
  - Auto-refresh when waiting for opponent

- **Friends System** - Add friends via unique friend codes
- **Authentication** - JWT-based with refresh tokens

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Go 1.23 |
| Database | SQLite |
| Frontend | React 18 + Vite |
| Reverse Proxy | Caddy (auto HTTPS) |
| Deployment | Docker Compose |

## Project Structure

```
.
├── backend/
│   ├── cmd/server/           # Application entrypoint
│   ├── internal/
│   │   ├── db/               # Database queries
│   │   ├── handlers/         # HTTP handlers
│   │   ├── middleware/       # Auth, CORS, logging
│   │   ├── models/           # Data structures
│   │   └── scrabble/         # Game logic, validation, dictionary
│   ├── Dockerfile
│   └── go.mod
├── frontend/
│   ├── src/
│   │   ├── components/       # Header, etc.
│   │   ├── context/          # AuthContext
│   │   ├── pages/            # Login, Games, ScrabbleGame, Friends, Profile
│   │   └── services/         # API client
│   ├── Dockerfile
│   └── package.json
├── Caddyfile                  # Production reverse proxy config
├── docker-compose.yml         # Development
├── docker-compose.prod.yml    # Production
└── DEPLOY.md                  # Deployment guide
```

## Getting Started

### Prerequisites

- Go 1.21+
- Node.js 20+
- Docker (for production)

### Development

**Backend:**
```bash
cd backend
export JWT_SECRET=dev-secret-change-me
go run cmd/server/main.go
```
API runs on `http://localhost:8080`

**Frontend:**
```bash
cd frontend
npm install
npm run dev
```
Dev server runs on `http://localhost:5173`

### Production (Docker)

See [DEPLOY.md](DEPLOY.md) for full deployment guide.

```bash
# Quick start
docker compose -f docker-compose.prod.yml up -d --build
```

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/register` | Register new user |
| POST | `/api/login` | Login, returns JWT |
| POST | `/api/refresh` | Refresh access token |
| GET | `/api/me` | Get current user |

### Friends

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/friends` | List friends |
| POST | `/api/friends/request` | Send friend request |
| GET | `/api/friends/requests` | Get pending requests |
| POST | `/api/friends/requests/{id}` | Accept/reject request |
| DELETE | `/api/friends/{id}` | Remove friend |

### Scrabble

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/scrabble/games` | List your games |
| POST | `/api/scrabble/games` | Create game with friend |
| GET | `/api/scrabble/games/{id}` | Get game state |
| POST | `/api/scrabble/games/{id}/play` | Submit a move |
| POST | `/api/scrabble/games/{id}/preview` | Preview move score |
| POST | `/api/scrabble/games/{id}/pass` | Pass turn |
| POST | `/api/scrabble/games/{id}/exchange` | Exchange tiles |
| POST | `/api/scrabble/games/{id}/resign` | Resign game |

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `PORT` | API server port | `8080` |
| `DATABASE_PATH` | SQLite database path | `./data/alecnfriends.db` |
| `JWT_SECRET` | Secret for JWT signing | (required) |
| `FRONTEND_URL` | Frontend URL for CORS | `http://localhost:5173` |

## License

Private project.
