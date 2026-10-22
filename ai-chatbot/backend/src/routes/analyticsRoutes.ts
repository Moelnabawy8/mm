import { Router, Request, Response } from 'express'

const router = Router()

router.get('/conversations', (req: Request, res: Response) => {
  res.json({
    success: true,
    data: {
      total_conversations: 0,
      avg_duration_seconds: 0,
      avg_messages_per_conversation: 0,
      top_intents: [],
      satisfaction_score: 0
    }
  })
})

export default router
