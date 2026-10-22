import { useState, useRef, useEffect } from 'react'
import { X, Send, Minimize2, Maximize2 } from 'lucide-react'
import { chatService } from '../services/chatService'
import MessageBubble from './MessageBubble'
import TypingIndicator from './TypingIndicator'
import QuickReplies from './QuickReplies'
import type { Message, QuickReply } from '../types'

interface ChatWidgetProps {
  isOpen: boolean
  onClose: () => void
}

export default function ChatWidget({ isOpen, onClose }: ChatWidgetProps) {
  const [messages, setMessages] = useState<Message[]>([])
  const [inputValue, setInputValue] = useState('')
  const [isTyping, setIsTyping] = useState(false)
  const [isMinimized, setIsMinimized] = useState(false)
  const [conversationId, setConversationId] = useState<string | null>(null)
  const [quickReplies, setQuickReplies] = useState<QuickReply[]>([])
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (isOpen && !conversationId) {
      initializeConversation()
    }
  }, [isOpen])

  useEffect(() => {
    scrollToBottom()
  }, [messages, isTyping])

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }

  const initializeConversation = async () => {
    try {
      const response = await chatService.startConversation()
      setConversationId(response.data.conversation_id)
      
      if (response.data.welcome_message) {
        const welcomeMessage: Message = {
          id: Date.now().toString(),
          sender: 'bot',
          content: response.data.welcome_message,
          timestamp: new Date(),
          intent: 'greeting'
        }
        setMessages([welcomeMessage])
      }
    } catch (error) {
      console.error('Failed to start conversation:', error)
    }
  }

  const handleSendMessage = async () => {
    if (!inputValue.trim() || !conversationId) return

    const userMessage: Message = {
      id: Date.now().toString(),
      sender: 'user',
      content: inputValue,
      timestamp: new Date()
    }

    setMessages(prev => [...prev, userMessage])
    setInputValue('')
    setIsTyping(true)
    setQuickReplies([])

    try {
      const response = await chatService.sendMessage(conversationId, inputValue)
      
      setTimeout(() => {
        const botMessage: Message = {
          id: response.data.message_id,
          sender: 'bot',
          content: response.data.response.text,
          timestamp: new Date(),
          intent: response.data.intent,
          confidence: response.data.confidence
        }
        
        setMessages(prev => [...prev, botMessage])
        setIsTyping(false)
        
        if (response.data.response.quick_replies?.length > 0) {
          setQuickReplies(response.data.response.quick_replies.map((text: string, index: number) => ({
            id: `qr-${index}`,
            text
          })))
        }
      }, 800)
    } catch (error) {
      console.error('Failed to send message:', error)
      setIsTyping(false)
      
      const errorMessage: Message = {
        id: Date.now().toString(),
        sender: 'bot',
        content: "I'm having trouble connecting right now. Please try again in a moment.",
        timestamp: new Date(),
        intent: 'error'
      }
      setMessages(prev => [...prev, errorMessage])
    }
  }

  const handleQuickReply = (reply: QuickReply) => {
    setInputValue(reply.text)
    setQuickReplies([])
    setTimeout(() => handleSendMessage(), 100)
  }

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSendMessage()
    }
  }

  if (!isOpen) return null

  return (
    <div className="fixed bottom-6 right-6 z-50">
      <div 
        className={`bg-white rounded-2xl shadow-2xl transition-all duration-300 ${
          isMinimized ? 'w-80 h-16' : 'w-96 h-[600px]'
        } flex flex-col`}
      >
        <div className="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 rounded-t-2xl flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
              <span className="text-2xl">🤖</span>
            </div>
            <div>
              <h3 className="font-semibold">AI Assistant</h3>
              <div className="flex items-center gap-1 text-xs">
                <div className="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                <span>Online</span>
              </div>
            </div>
          </div>
          
          <div className="flex items-center gap-2">
            <button
              onClick={() => setIsMinimized(!isMinimized)}
              className="p-1.5 hover:bg-white/20 rounded-lg transition-colors"
            >
              {isMinimized ? <Maximize2 size={18} /> : <Minimize2 size={18} />}
            </button>
            <button
              onClick={onClose}
              className="p-1.5 hover:bg-white/20 rounded-lg transition-colors"
            >
              <X size={18} />
            </button>
          </div>
        </div>

        {!isMinimized && (
          <>
            <div className="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50">
              {messages.map((message) => (
                <MessageBubble key={message.id} message={message} />
              ))}
              
              {isTyping && <TypingIndicator />}
              
              {quickReplies.length > 0 && (
                <QuickReplies replies={quickReplies} onSelect={handleQuickReply} />
              )}
              
              <div ref={messagesEndRef} />
            </div>

            <div className="p-4 border-t bg-white rounded-b-2xl">
              <div className="flex items-center gap-2">
                <input
                  ref={inputRef}
                  type="text"
                  value={inputValue}
                  onChange={(e) => setInputValue(e.target.value)}
                  onKeyPress={handleKeyPress}
                  placeholder="Type your message..."
                  className="flex-1 px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
                <button
                  onClick={handleSendMessage}
                  disabled={!inputValue.trim()}
                  className="p-3 bg-blue-600 text-white rounded-full hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                >
                  <Send size={20} />
                </button>
              </div>
              <p className="text-xs text-gray-500 mt-2 text-center">
                Press Enter to send • Shift+Enter for new line
              </p>
            </div>
          </>
        )}
      </div>
    </div>
  )
}
