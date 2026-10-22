export interface Message {
  id: string
  sender: 'user' | 'bot'
  content: string
  timestamp: Date
  intent?: string
  confidence?: number
}

export interface QuickReply {
  id: string
  text: string
}

export interface Conversation {
  id: string
  user_id: string
  session_id: string
  started_at: string
  ended_at?: string
  status: 'active' | 'ended'
}

export interface Intent {
  intent: string
  patterns: string[]
  responses: string[]
  priority: 'low' | 'medium' | 'high' | 'critical'
  category?: string
}

export interface ConversationContext {
  conversation_state: string
  last_intent: string
  user_data?: Record<string, any>
}
