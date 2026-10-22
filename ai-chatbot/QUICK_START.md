# 🚀 AI Chatbot - Quick Start Guide

## 📋 Table of Contents
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Running the Application](#running-the-application)
- [Testing](#testing)
- [Common Issues](#common-issues)

---

## Prerequisites

Before you begin, ensure you have:
- **Node.js** 18+ installed ([Download](https://nodejs.org/))
- **npm** or **yarn** package manager
- **Git** for version control
- Code editor (VS Code recommended)

---

## Installation

### 1. Frontend Setup

```bash
# Navigate to frontend directory
cd ai-chatbot/frontend

# Install dependencies
npm install

# Create environment file
echo "VITE_API_URL=http://localhost:8000" > .env

# Start development server
npm run dev
```

Frontend will be available at: **http://localhost:3000**

---

### 2. Backend Setup

```bash
# Navigate to backend directory
cd ai-chatbot/backend

# Install dependencies
npm install

# Create environment file
cp .env.example .env

# Edit .env file with your settings
# PORT=8000
# NODE_ENV=development

# Start development server
npm run dev
```

Backend API will be available at: **http://localhost:8000**

---

## Running the Application

### Development Mode

**Terminal 1 - Frontend:**
```bash
cd ai-chatbot/frontend
npm run dev
```

**Terminal 2 - Backend:**
```bash
cd ai-chatbot/backend
npm run dev
```

### Production Build

**Frontend:**
```bash
cd ai-chatbot/frontend
npm run build
npm run preview
```

**Backend:**
```bash
cd ai-chatbot/backend
npm run build
npm start
```

---

## Testing the Chatbot

### 1. Open Browser
Navigate to: `http://localhost:3000`

### 2. Click "Start Chatting Now"

### 3. Try These Sample Conversations

**Greeting:**
```
You: Hi
Bot: Hi there! 👋 How can I help you today?
```

**Product Inquiry:**
```
You: What services do you offer?
Bot: We offer comprehensive business solutions including:
     • Customer Support Automation
     • Sales & Marketing Tools
     ...
```

**Pricing:**
```
You: How much does it cost?
Bot: Our pricing plans:
     📦 Starter: $49/month
     📦 Professional: $99/month
     ...
```

**Help Request:**
```
You: I need help
Bot: I'd be happy to help! What do you need assistance with?
```

---

## API Endpoints

### Health Check
```bash
curl http://localhost:8000/health
```

### Start Conversation
```bash
curl -X POST http://localhost:8000/api/v1/conversations \
  -H "Content-Type: application/json" \
  -d '{"user_id": "test_user", "channel": "web"}'
```

### Send Message
```bash
curl -X POST http://localhost:8000/api/v1/conversations/{conversation_id}/messages \
  -H "Content-Type: application/json" \
  -d '{"message": "Hello"}'
```

---

## Project Structure

```
ai-chatbot/
├── frontend/
│   ├── src/
│   │   ├── components/
│   │   │   ├── ChatWidget.tsx
│   │   │   ├── MessageBubble.tsx
│   │   │   ├── TypingIndicator.tsx
│   │   │   └── QuickReplies.tsx
│   │   ├── services/
│   │   │   └── chatService.ts
│   │   ├── types/
│   │   │   └── index.ts
│   │   ├── App.tsx
│   │   └── main.tsx
│   ├── package.json
│   └── vite.config.ts
│
├── backend/
│   ├── src/
│   │   ├── controllers/
│   │   │   └── ConversationController.ts
│   │   ├── services/
│   │   │   ├── IntentDetector.ts
│   │   │   └── ResponseGenerator.ts
│   │   ├── config/
│   │   │   └── intents.ts
│   │   ├── middleware/
│   │   │   ├── errorHandler.ts
│   │   │   └── rateLimiter.ts
│   │   ├── routes/
│   │   │   ├── conversationRoutes.ts
│   │   │   └── analyticsRoutes.ts
│   │   ├── types/
│   │   │   └── index.ts
│   │   └── server.ts
│   ├── package.json
│   └── tsconfig.json
│
├── README.md
├── DEPLOYMENT_GUIDE.md
└── QUICK_START.md
```

---

## Common Issues

### Issue 1: Port Already in Use

**Error:** `Port 3000 is already in use`

**Solution:**
```bash
# Kill process on port 3000
# Windows
netstat -ano | findstr :3000
taskkill /PID <PID> /F

# Linux/Mac
lsof -ti:3000 | xargs kill -9
```

---

### Issue 2: Module Not Found

**Error:** `Cannot find module 'react'`

**Solution:**
```bash
# Delete node_modules and reinstall
rm -rf node_modules package-lock.json
npm install
```

---

### Issue 3: CORS Error

**Error:** `Access to fetch blocked by CORS policy`

**Solution:**
Check backend `.env` file:
```env
CORS_ORIGIN=http://localhost:3000
```

---

### Issue 4: TypeScript Errors

**Error:** Various TypeScript compilation errors

**Solution:**
```bash
# Frontend
cd frontend
npm install --save-dev @types/react @types/react-dom

# Backend
cd backend
npm install --save-dev @types/node @types/express
```

---

## Environment Variables

### Frontend (.env)
```env
VITE_API_URL=http://localhost:8000
```

### Backend (.env)
```env
PORT=8000
NODE_ENV=development
CORS_ORIGIN=http://localhost:3000
JWT_SECRET=your-secret-key
```

---

## Next Steps

1. ✅ **Customize Intents**
   - Edit `backend/src/config/intents.ts`
   - Add your own conversation patterns

2. ✅ **Modify UI**
   - Update colors in `frontend/tailwind.config.js`
   - Customize components in `frontend/src/components/`

3. ✅ **Add Features**
   - Implement user authentication
   - Add conversation history
   - Integrate with your database

4. ✅ **Deploy**
   - Follow `DEPLOYMENT_GUIDE.md`
   - Deploy frontend to Netlify/Vercel
   - Deploy backend to Heroku/AWS

---

## Support

For detailed documentation, see:
- **Full Documentation:** `README.md`
- **Deployment Guide:** `DEPLOYMENT_GUIDE.md`
- **API Specification:** Check README.md API section

---

## Tips for Development

### Hot Reload
Both frontend and backend support hot reload. Changes will reflect automatically.

### Debugging
```bash
# Frontend - Check browser console
# Backend - Check terminal output
```

### Add New Intent
1. Edit `backend/src/config/intents.ts`
2. Add new intent object
3. Restart backend server
4. Test in chatbot

### Customize Responses
Edit the `responses` array in each intent to change bot replies.

---

**Happy Coding! 🎉**

If you encounter any issues, check the full README.md or create an issue in the repository.
