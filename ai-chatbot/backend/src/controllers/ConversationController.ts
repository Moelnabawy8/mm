import { Request, Response } from 'express'
import { v4 as uuidv4 } from 'uuid'
import { IntentDetector } from '../services/IntentDetector'
import { ResponseGenerator } from '../services/ResponseGenerator'
import { ConversationContext, Message, Conversation } from '../types'

const conversations = new Map<string, Conversation>()
const messages = new Map<string, Message[]>()
const contexts = new Map<string, ConversationContext>()

export class ConversationController {
  private intentDetector: IntentDetector
  private responseGenerator: ResponseGenerator

  constructor() {
    this.intentDetector = new IntentDetector()
    this.responseGenerator = new ResponseGenerator()
  }

  startConversation = async (req: Request, res: Response) => {
    try {
      const { user_id, channel } = req.body
      
      const conversationId = uuidv4()
      const sessionId = uuidv4()
      
      const conversation: Conversation = {
        id: conversationId,
        user_id: user_id || 'guest',
        session_id: sessionId,
        started_at: new Date(),
        status: 'active',
        created_by: user_id || 'guest'
      }
      
      conversations.set(conversationId, conversation)
      messages.set(conversationId, [])
      
      const context: ConversationContext = {
        conversation_id: conversationId,
        conversation_state: 'active',
        message_count: 0,
        user_data: {}
      }
      contexts.set(conversationId, context)
      
      res.status(201).json({
        success: true,
        data: {
          conversation_id: conversationId,
          session_id: sessionId,
          started_at: conversation.started_at,
          welcome_message: 'Hi! 👋 I\'m your AI assistant. How can I help you today?'
        }
      })
    } catch (error) {
      res.status(500).json({
        success: false,
        error: 'Failed to start conversation'
      })
    }
  }

  sendMessage = async (req: Request, res: Response) => {
    try {
      const { conversationId } = req.params
      const { message: userMessage } = req.body
      
      const conversation = conversations.get(conversationId)
      if (!conversation) {
        return res.status(404).json({
          success: false,
          error: 'Conversation not found'
        })
      }
      
      const context = contexts.get(conversationId)
      if (!context) {
        return res.status(404).json({
          success: false,
          error: 'Context not found'
        })
      }
      
      const userMsg: Message = {
        id: uuidv4(),
        conversation_id: conversationId,
        sender: 'user',
        content: userMessage,
        created_at: new Date()
      }
      
      const conversationMessages = messages.get(conversationId) || []
      conversationMessages.push(userMsg)
      
      const intentMatch = this.intentDetector.detectIntent(userMessage)
      
      context.last_intent = context.current_intent
      context.current_intent = intentMatch.intent
      context.message_count++
      
      const response = this.responseGenerator.generateResponse(
        intentMatch.intent,
        context
      )
      
      const botMsg: Message = {
        id: uuidv4(),
        conversation_id: conversationId,
        sender: 'bot',
        content: response.text,
        intent: intentMatch.intent,
        confidence: intentMatch.confidence,
        created_at: new Date()
      }
      
      conversationMessages.push(botMsg)
      messages.set(conversationId, conversationMessages)
      contexts.set(conversationId, context)
      
      res.json({
        success: true,
        data: {
          message_id: botMsg.id,
          intent: intentMatch.intent,
          confidence: intentMatch.confidence,
          response: {
            text: response.text,
            quick_replies: response.quick_replies || [],
            attachments: []
          },
          context: {
            conversation_state: context.conversation_state,
            last_intent: context.last_intent || ''
          }
        }
      })
    } catch (error) {
      res.status(500).json({
        success: false,
        error: 'Failed to process message'
      })
    }
  }

  getMessages = async (req: Request, res: Response) => {
    try {
      const { conversationId } = req.params
      const { limit = 50, offset = 0 } = req.query
      
      const conversationMessages = messages.get(conversationId) || []
      
      const paginatedMessages = conversationMessages.slice(
        Number(offset),
        Number(offset) + Number(limit)
      )
      
      res.json({
        success: true,
        data: {
          messages: paginatedMessages,
          pagination: {
            total: conversationMessages.length,
            limit: Number(limit),
            offset: Number(offset)
          }
        }
      })
    } catch (error) {
      res.status(500).json({
        success: false,
        error: 'Failed to retrieve messages'
      })
    }
  }

  endConversation = async (req: Request, res: Response) => {
    try {
      const { conversationId } = req.params
      
      const conversation = conversations.get(conversationId)
      if (!conversation) {
        return res.status(404).json({
          success: false,
          error: 'Conversation not found'
        })
      }
      
      conversation.ended_at = new Date()
      conversation.status = 'ended'
      conversations.set(conversationId, conversation)
      
      const conversationMessages = messages.get(conversationId) || []
      const duration = conversation.ended_at.getTime() - conversation.started_at.getTime()
      
      res.json({
        success: true,
        data: {
          conversation_id: conversationId,
          ended_at: conversation.ended_at,
          duration_seconds: Math.floor(duration / 1000),
          message_count: conversationMessages.length
        }
      })
    } catch (error) {
      res.status(500).json({
        success: false,
        error: 'Failed to end conversation'
      })
    }
  }
}
