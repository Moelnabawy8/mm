# 🚀 AI Chatbot Deployment Guide

## Prerequisites

- Node.js 18+ installed
- npm or yarn package manager
- PostgreSQL database (optional for production)
- Redis server (optional for production)

---

## 📦 Frontend Deployment

### 1. Install Dependencies
```bash
cd ai-chatbot/frontend
npm install
```

### 2. Configure Environment
Create `.env` file:
```env
VITE_API_URL=http://localhost:8000
```

### 3. Development Mode
```bash
npm run dev
```
Access at: http://localhost:3000

### 4. Production Build
```bash
npm run build
npm run preview
```

### 5. Deploy to Production
**Option A: Netlify**
```bash
npm run build
# Upload dist/ folder to Netlify
```

**Option B: Vercel**
```bash
vercel --prod
```

---

## 🔧 Backend Deployment

### 1. Install Dependencies
```bash
cd ai-chatbot/backend
npm install
```

### 2. Configure Environment
Copy `.env.example` to `.env` and update:
```env
PORT=8000
NODE_ENV=production
DATABASE_URL=your_database_url
REDIS_URL=your_redis_url
JWT_SECRET=your_secure_secret
```

### 3. Development Mode
```bash
npm run dev
```
Access at: http://localhost:8000

### 4. Production Build
```bash
npm run build
npm start
```

### 5. Deploy to Production
**Option A: Heroku**
```bash
heroku create your-chatbot-api
git push heroku main
```

**Option B: Digital Ocean / AWS**
```bash
# Build
npm run build

# Start with PM2
pm2 start dist/server.js --name chatbot-api
pm2 save
pm2 startup
```

---

## 🗄️ Database Setup (Optional)

### PostgreSQL Schema
```sql
CREATE TABLE conversations (
  id UUID PRIMARY KEY,
  user_id VARCHAR(255),
  session_id VARCHAR(255),
  started_at TIMESTAMP,
  ended_at TIMESTAMP,
  status VARCHAR(50),
  created_by VARCHAR(255)
);

CREATE TABLE messages (
  id UUID PRIMARY KEY,
  conversation_id UUID REFERENCES conversations(id),
  sender VARCHAR(10),
  content TEXT,
  intent VARCHAR(100),
  confidence DECIMAL(3,2),
  created_at TIMESTAMP
);

CREATE TABLE intents (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100),
  patterns TEXT[],
  responses TEXT[],
  priority VARCHAR(20),
  category VARCHAR(50)
);

CREATE INDEX idx_conversations_user ON conversations(user_id);
CREATE INDEX idx_messages_conversation ON messages(conversation_id);
```

---

## 🔐 Security Checklist

- [ ] Change JWT_SECRET to a strong random string
- [ ] Enable HTTPS in production
- [ ] Configure CORS properly
- [ ] Set up rate limiting
- [ ] Enable helmet security headers
- [ ] Sanitize user inputs
- [ ] Set up monitoring and logging
- [ ] Regular security updates

---

## 📊 Monitoring

### Health Check
```bash
curl http://localhost:8000/health
```

### Logs
```bash
# PM2 logs
pm2 logs chatbot-api

# Docker logs
docker logs chatbot-api
```

---

## 🐳 Docker Deployment

### Frontend Dockerfile
```dockerfile
FROM node:18-alpine
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build
EXPOSE 3000
CMD ["npm", "run", "preview"]
```

### Backend Dockerfile
```dockerfile
FROM node:18-alpine
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build
EXPOSE 8000
CMD ["npm", "start"]
```

### Docker Compose
```yaml
version: '3.8'
services:
  frontend:
    build: ./frontend
    ports:
      - "3000:3000"
    environment:
      - VITE_API_URL=http://backend:8000
  
  backend:
    build: ./backend
    ports:
      - "8000:8000"
    environment:
      - PORT=8000
      - NODE_ENV=production
    depends_on:
      - redis
      - postgres
  
  redis:
    image: redis:alpine
    ports:
      - "6379:6379"
  
  postgres:
    image: postgres:15-alpine
    environment:
      - POSTGRES_DB=chatbot_db
      - POSTGRES_USER=chatbot
      - POSTGRES_PASSWORD=secure_password
    ports:
      - "5432:5432"
```

---

## 🧪 Testing

### Frontend Tests
```bash
cd frontend
npm test
```

### Backend Tests
```bash
cd backend
npm test
```

### E2E Tests
```bash
npm run test:e2e
```

---

## 📈 Performance Optimization

1. **Frontend**
   - Enable code splitting
   - Lazy load components
   - Optimize images
   - Use CDN for static assets

2. **Backend**
   - Enable Redis caching
   - Database query optimization
   - Connection pooling
   - Load balancing

---

## 🔄 CI/CD Pipeline

### GitHub Actions Example
```yaml
name: Deploy Chatbot

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup Node.js
        uses: actions/setup-node@v2
        with:
          node-version: '18'
      - name: Install & Build
        run: |
          npm install
          npm run build
      - name: Deploy
        run: npm run deploy
```

---

## 📞 Support

For issues or questions:
- Check the README.md
- Review the API documentation
- Contact: support@yourcompany.com

---

**Version**: 1.0.0  
**Last Updated**: 2024
