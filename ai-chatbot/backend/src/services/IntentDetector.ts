import natural from 'natural'
import { Intent, IntentMatch } from '../types'
import { intentsDatabase } from '../config/intents'

const tokenizer = new natural.WordTokenizer()
const TfIdf = natural.TfIdf
const tfidf = new TfIdf()

export class IntentDetector {
  private intents: Intent[]
  
  constructor() {
    this.intents = intentsDatabase
    this.trainModel()
  }

  private trainModel(): void {
    this.intents.forEach(intent => {
      intent.patterns.forEach(pattern => {
        tfidf.addDocument(pattern.toLowerCase())
      })
    })
  }

  private normalizeText(text: string): string {
    return text.toLowerCase().trim().replace(/[^\w\s]/g, '')
  }

  private tokenize(text: string): string[] {
    return tokenizer.tokenize(this.normalizeText(text)) || []
  }

  private calculateSimilarity(userTokens: string[], patternTokens: string[]): number {
    const userSet = new Set(userTokens)
    const patternSet = new Set(patternTokens)
    
    const intersection = new Set([...userSet].filter(x => patternSet.has(x)))
    const union = new Set([...userSet, ...patternSet])
    
    if (union.size === 0) return 0
    
    return intersection.size / union.size
  }

  private getPriorityValue(priority: string): number {
    const priorities: Record<string, number> = {
      'critical': 4,
      'high': 3,
      'medium': 2,
      'low': 1
    }
    return priorities[priority] || 0
  }

  public detectIntent(userInput: string): IntentMatch {
    const normalized = this.normalizeText(userInput)
    const userTokens = this.tokenize(userInput)
    
    const matches: IntentMatch[] = []
    
    for (const intent of this.intents) {
      let maxScore = 0
      
      for (const pattern of intent.patterns) {
        const patternTokens = this.tokenize(pattern)
        const similarity = this.calculateSimilarity(userTokens, patternTokens)
        
        if (similarity > maxScore) {
          maxScore = similarity
        }
        
        if (normalized.includes(pattern.toLowerCase())) {
          maxScore = Math.max(maxScore, 0.8)
        }
      }
      
      if (maxScore > 0.3) {
        matches.push({
          intent: intent.intent,
          confidence: maxScore,
          priority: intent.priority,
          category: intent.category
        })
      }
    }
    
    matches.sort((a, b) => {
      if (a.confidence !== b.confidence) {
        return b.confidence - a.confidence
      }
      return this.getPriorityValue(b.priority) - this.getPriorityValue(a.priority)
    })
    
    if (matches.length > 0) {
      return matches[0]
    }
    
    return {
      intent: 'fallback',
      confidence: 0,
      priority: 'low'
    }
  }

  public getIntentByName(intentName: string): Intent | undefined {
    return this.intents.find(i => i.intent === intentName)
  }
}
