import { Intent, ConversationContext } from '../types'
import { IntentDetector } from './IntentDetector'

export class ResponseGenerator {
  private intentDetector: IntentDetector

  constructor() {
    this.intentDetector = new IntentDetector()
  }

  public generateResponse(
    intentName: string,
    context: ConversationContext
  ): { text: string; quick_replies?: string[] } {
    const intent = this.intentDetector.getIntentByName(intentName)
    
    if (!intent) {
      return {
        text: "I'm not sure how to respond to that. Could you rephrase?",
        quick_replies: ['Help', 'Start Over', 'Talk to Human']
      }
    }

    const responseText = this.selectResponse(intent.responses, context)
    const quickReplies = this.generateQuickReplies(intentName, context)

    return {
      text: this.personalizeResponse(responseText, context),
      quick_replies: quickReplies
    }
  }

  private selectResponse(responses: string[], context: ConversationContext): string {
    if (context.last_intent === context.current_intent) {
      const alternativeResponses = responses.filter(r => !r.includes('?'))
      if (alternativeResponses.length > 0) {
        return alternativeResponses[Math.floor(Math.random() * alternativeResponses.length)]
      }
    }
    
    return responses[Math.floor(Math.random() * responses.length)]
  }

  private personalizeResponse(response: string, context: ConversationContext): string {
    let personalized = response
    
    if (context.user_data?.name) {
      personalized = personalized.replace('{name}', context.user_data.name)
    }
    
    if (context.user_data?.company) {
      personalized = personalized.replace('{company}', context.user_data.company)
    }
    
    return personalized
  }

  private generateQuickReplies(intentName: string, context: ConversationContext): string[] | undefined {
    const quickRepliesMap: Record<string, string[]> = {
      'greeting': ['View Services', 'Pricing', 'Contact Support'],
      'help_request': ['Technical Issue', 'Billing Question', 'General Inquiry'],
      'product_inquiry': ['Features', 'Pricing', 'Demo'],
      'pricing_inquiry': ['Starter Plan', 'Professional Plan', 'Enterprise Plan'],
      'complaint': ['Describe Issue', 'Request Refund', 'Speak to Manager'],
      'goodbye': []
    }

    return quickRepliesMap[intentName]
  }
}
