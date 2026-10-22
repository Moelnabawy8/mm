import express from 'express'
import cors from 'cors'
import helmet from 'helmet'
import dotenv from 'dotenv'
import { errorHandler } from './middleware/errorHandler'
import { rateLimiter } from './middleware/rateLimiter'
import conversationRoutes from './routes/conversationRoutes'
import analyticsRoutes from './routes/analyticsRoutes'

dotenv.config()

const app = express()
const PORT = process.env.PORT || 8000

app.use(helmet())
app.use(cors())
app.use(express.json())
app.use(express.urlencoded({ extended: true }))
app.use(rateLimiter)

app.get('/health', (req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString() })
})

app.use('/api/v1/conversations', conversationRoutes)
app.use('/api/v1/analytics', analyticsRoutes)

app.use(errorHandler)

app.listen(PORT, () => {
  console.log(`🤖 AI Chatbot API running on port ${PORT}`)
  console.log(`📊 Health check: http://localhost:${PORT}/health`)
})

export default app
