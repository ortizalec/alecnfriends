# Deployment Guide for alecnfriends.com

## Prerequisites
- A Digital Ocean account
- Your domain (alecnfriends.com) on GoDaddy

---

## Step 1: Create a Digital Ocean Droplet

1. Log into [Digital Ocean](https://cloud.digitalocean.com/)
2. Click **Create** → **Droplets**
3. Choose:
   - **Region**: Choose closest to your users
   - **Image**: Ubuntu 24.04 LTS
   - **Size**: Basic → Regular → $6/mo (1GB RAM) is fine to start
   - **Authentication**: SSH Key (recommended) or Password
4. Click **Create Droplet**
5. Note the IP address (e.g., `164.90.xxx.xxx`)

---

## Step 2: Point Your Domain to Digital Ocean

### In GoDaddy DNS Settings:

1. Go to [GoDaddy](https://godaddy.com) → My Products → DNS
2. Delete any existing A records for @ and www
3. Add these DNS records:

| Type | Name | Value | TTL |
|------|------|-------|-----|
| A | @ | YOUR_DROPLET_IP | 600 |
| A | www | YOUR_DROPLET_IP | 600 |

4. Wait 5-30 minutes for DNS to propagate
5. Verify with: `nslookup alecnfriends.com`

---

## Step 3: Set Up the Server

### SSH into your droplet:
```bash
ssh root@YOUR_DROPLET_IP
```

### Install Docker:
```bash
# Update system
apt update && apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com | sh

# Install Docker Compose
apt install docker-compose-plugin -y

# Verify installation
docker --version
docker compose version
```

### Create app directory:
```bash
mkdir -p /opt/alecnfriends
cd /opt/alecnfriends
```

---

## Step 4: Deploy the Application

### Option A: Clone from Git (if repo is public/accessible)
```bash
git clone https://github.com/YOUR_USERNAME/alecnfriends.git .
```

### Option B: Copy files from local machine
From your local machine:
```bash
# From the alecnfriends directory
rsync -avz --exclude 'node_modules' --exclude '.git' --exclude 'data' \
  ./ root@YOUR_DROPLET_IP:/opt/alecnfriends/
```

### Create environment file:
```bash
cd /opt/alecnfriends

# Generate a secure JWT secret
JWT_SECRET=$(openssl rand -hex 32)

# Create .env file
cat > .env << EOF
JWT_SECRET=$JWT_SECRET
DATABASE_PATH=/app/data/alecnfriends.db
FRONTEND_URL=https://alecnfriends.com
EOF

# Verify
cat .env
```

### Build and start:
```bash
docker compose -f docker-compose.prod.yml up -d --build
```

### Check status:
```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f
```

---

## Step 5: Verify HTTPS

Caddy automatically obtains Let's Encrypt certificates. After a minute or two:

1. Visit https://alecnfriends.com
2. You should see the app with a valid HTTPS certificate
3. Check the padlock icon in your browser

---

## Useful Commands

### View logs:
```bash
# All services
docker compose -f docker-compose.prod.yml logs -f

# Specific service
docker compose -f docker-compose.prod.yml logs -f backend
docker compose -f docker-compose.prod.yml logs -f frontend
docker compose -f docker-compose.prod.yml logs -f caddy
```

### Restart services:
```bash
docker compose -f docker-compose.prod.yml restart
```

### Update and redeploy:
```bash
cd /opt/alecnfriends
git pull  # or rsync new files
docker compose -f docker-compose.prod.yml up -d --build
```

### Stop everything:
```bash
docker compose -f docker-compose.prod.yml down
```

### View database:
```bash
docker compose -f docker-compose.prod.yml exec backend sh
sqlite3 /app/data/alecnfriends.db
```

### Backup database:
```bash
docker cp alecnfriends-backend:/app/data/alecnfriends.db ./backup-$(date +%Y%m%d).db
```

---

## Troubleshooting

### SSL Certificate Issues
```bash
# Check Caddy logs
docker compose -f docker-compose.prod.yml logs caddy

# Restart Caddy to retry certificate
docker compose -f docker-compose.prod.yml restart caddy
```

### DNS not resolving
```bash
# Check DNS propagation
nslookup alecnfriends.com
dig alecnfriends.com

# Wait longer or try flushing DNS cache
```

### Container won't start
```bash
# Check logs for errors
docker compose -f docker-compose.prod.yml logs backend
docker compose -f docker-compose.prod.yml logs frontend

# Rebuild from scratch
docker compose -f docker-compose.prod.yml down
docker compose -f docker-compose.prod.yml up -d --build --force-recreate
```

### Backend health check
```bash
curl http://localhost:8080/api/health
# Should return: {"status":"ok"}
```

---

## Security Recommendations

1. **Set up a firewall:**
```bash
ufw allow OpenSSH
ufw allow 80
ufw allow 443
ufw enable
```

2. **Create a non-root user:**
```bash
adduser deploy
usermod -aG docker deploy
```

3. **Set up automatic security updates:**
```bash
apt install unattended-upgrades -y
dpkg-reconfigure -plow unattended-upgrades
```

4. **Regular backups** of `/opt/alecnfriends/data/` directory
