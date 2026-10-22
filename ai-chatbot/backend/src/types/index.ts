export interface Intent {
  intent: string
  patterns: string[]
  responses: string[]
  priority: 'low' | 'medium' | 'high' | 'critical'
  category?: string
}

export interface IntentMatch {
  intent: string
  confidence: number
  priority: string
  category?: string
}

export interface Message {
  id: string
  conversation_id: string
  sender: 'user' | 'bot'
  content: string
  intent?: string
  confidence?: number
  created_at: Date
}

export interface Conversation {
  id: string
  user_id: string
  session_id: string
  started_at: Date
  ended_at?: Date
  status: 'active' | 'ended'
  created_by: string
}

export interface ConversationContext {
  conversation_id: string
  conversation_state: string
  current_intent?: string
  last_intent?: string
  message_count: number
  user_data?: Record<string, any>
}

export interface ApiResponse<T = any> {
  success: boolean
  data?: T
  error?: string
  message?: string
}
