import type { QuickReply } from '../types'

interface QuickRepliesProps {
  replies: QuickReply[]
  onSelect: (reply: QuickReply) => void
}

export default function QuickReplies({ replies, onSelect }: QuickRepliesProps) {
  return (
    <div className="flex flex-wrap gap-2 justify-start">
      {replies.map((reply) => (
        <button
          key={reply.id}
          onClick={() => onSelect(reply)}
          className="px-4 py-2 bg-white border-2 border-blue-500 text-blue-600 rounded-full text-sm font-medium hover:bg-blue-50 transition-colors shadow-sm"
        >
          {reply.text}
        </button>
      ))}
    </div>
  )
}
