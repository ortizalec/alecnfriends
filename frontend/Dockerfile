# Build stage
FROM node:20-alpine AS builder

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci

# Copy source code
COPY . .

# Build the app
RUN npm run build

# Output stage - copies files to shared volume then sleeps
FROM alpine:latest

WORKDIR /app

# Copy built assets from builder
COPY --from=builder /app/dist /app/dist

# Copy files to the shared volume on startup, then keep container alive
# (Container needs to stay running for docker-compose depends_on to work)
CMD ["sh", "-c", "cp -r /app/dist/* /srv/ && echo 'Frontend files copied to /srv' && tail -f /dev/null"]
