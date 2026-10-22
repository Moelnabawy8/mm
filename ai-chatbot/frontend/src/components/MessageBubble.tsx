import { format } from 'date-fns'
import type { Message } from '../types'

interface MessageBubbleProps {
  message: Message
}

export default function MessageBubble({ message }: MessageBubbleProps) {
  const isBot = message.sender === 'bot'
  
  return (
    <div className={`flex ${isBot ? 'justify-start' : 'justify-end'} animate-fadeIn`}>
      <div className={`max-w-[80%] ${isBot ? 'order-2' : 'order-1'}`}>
        <div
          className={`rounded-2xl px-4 py-3 ${
            isBot
              ? 'bg-white text-gray-800 shadow-md'
              : 'bg-gradient-to-r from-blue-600 to-purple-600 text-white'
          }`}
        >
          <p className="text-sm leading-relaxed whitespace-pre-wrap">{message.content}</p>
          
          {message.confidence && message.confidence < 0.7 && isBot && (
            <div className="mt-2 pt-2 border-t border-gray-200">
              <p className="text-xs text-gray-500">
                Not sure I got that right. Could you rephrase?
              </p>
            </div>
          )}
        </div>
        
        <div className={`flex items-center gap-2 mt-1 px-2 ${isBot ? 'justify-start' : 'justify-end'}`}>
          <span className="text-xs text-gray-500">
            {format(message.timestamp, 'HH:mm')}
          </span>
          {message.intent && (
            <span className="text-xs text-gray-400">• {message.intent}</span>
          )}
        </div>
      </div>
      
      {isBot && (
        <div className="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white text-sm font-semibold order-1 mr-2 flex-shrink-0">
          AI
        </div>
      )}
    </div>
  )
}
