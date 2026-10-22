import axios from 'axios'

const API_BASE_URL = '/api/v1'

export interface StartConversationResponse {
  success: boolean
  data: {
    conversation_id: string
    session_id: string
    started_at: string
    welcome_message: string
  }
}

export interface SendMessageResponse {
  success: boolean
  data: {
    message_id: string
    intent: string
    confidence: number
    response: {
      text: string
      quick_replies?: string[]
      attachments?: any[]
    }
    context: {
      conversation_state: string
      last_intent: string
    }
  }
}

class ChatService {
  async startConversation(): Promise<StartConversationResponse> {
    const response = await axios.post(`${API_BASE_URL}/conversations`, {
      user_id: 'guest',
      channel: 'web'
    })
    return response.data
  }

  async sendMessage(conversationId: string, message: string): Promise<SendMessageResponse> {
    const response = await axios.post(
      `${API_BASE_URL}/conversations/${conversationId}/messages`,
      {
        message,
        metadata: {
          user_agent: navigator.userAgent,
          ip_address: 'client'
        }
      }
    )
    return response.data
  }

  async getHistory(conversationId: string, limit = 50, offset = 0) {
    const response = await axios.get(
      `${API_BASE_URL}/conversations/${conversationId}/messages`,
      {
        params: { limit, offset }
      }
    )
    return response.data
  }

  async endConversation(conversationId: string) {
    const response = await axios.post(
      `${API_BASE_URL}/conversations/${conversationId}/end`
    )
    return response.data
  }
}

export const chatService = new ChatService()
