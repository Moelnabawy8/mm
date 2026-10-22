import { Intent } from '../types'

export const intentsDatabase: Intent[] = [
  {
    intent: 'greeting',
    patterns: [
      'hi', 'hello', 'hey', 'good morning', 'good afternoon',
      'good evening', 'what\'s up', 'howdy', 'greetings', 'yo'
    ],
    responses: [
      'Hi there! 👋 How can I help you today?',
      'Hello! What can I do for you?',
      'Hey! I\'m here to assist. What do you need?',
      'Greetings! How may I help you?'
    ],
    priority: 'high',
    category: 'general'
  },
  {
    intent: 'help_request',
    patterns: [
      'help', 'assist', 'support', 'can you help',
      'i need help', 'help me', 'assistance', 'need support'
    ],
    responses: [
      'I\'d be happy to help! What do you need assistance with?',
      'Sure! Tell me what you\'re trying to do.',
      'I\'m here to help. What\'s the issue?',
      'Of course! How can I assist you?'
    ],
    priority: 'high',
    category: 'support'
  },
  {
    intent: 'product_inquiry',
    patterns: [
      'what do you offer', 'your services', 'products',
      'what can you do', 'features', 'capabilities', 'services'
    ],
    responses: [
      'We offer comprehensive business solutions including:\n• Customer Support Automation\n• Sales & Marketing Tools\n• Analytics & Reporting\n• Integration Services\n\nWhich area interests you most?',
      'Our platform provides:\n✓ 24/7 AI Support\n✓ Multi-channel Integration\n✓ Advanced Analytics\n✓ Custom Workflows\n\nWould you like details on any of these?'
    ],
    priority: 'medium',
    category: 'sales'
  },
  {
    intent: 'pricing_inquiry',
    patterns: [
      'price', 'cost', 'how much', 'pricing', 'rates',
      'fees', 'payment', 'subscription', 'plans'
    ],
    responses: [
      'Our pricing plans:\n\n📦 Starter: $49/month\n• Up to 1,000 conversations\n• Basic features\n• Email support\n\n📦 Professional: $99/month\n• Up to 10,000 conversations\n• Advanced features\n• Priority support\n\n📦 Enterprise: Custom pricing\n• Unlimited conversations\n• All features\n• Dedicated support\n\nWhich plan fits your needs?',
      'We have flexible pricing starting at $49/month. Would you like to see a detailed comparison of our plans?'
    ],
    priority: 'high',
    category: 'sales'
  },
  {
    intent: 'complaint',
    patterns: [
      'problem', 'issue', 'not working', 'broken', 'error',
      'complaint', 'frustrated', 'disappointed', 'angry', 'upset'
    ],
    responses: [
      'I\'m sorry you\'re experiencing issues. Can you describe what\'s happening?',
      'I understand your frustration. Let\'s get this sorted out. What\'s the problem?',
      'I apologize for the inconvenience. Please tell me more about the issue so I can help.'
    ],
    priority: 'critical',
    category: 'support'
  },
  {
    intent: 'technical_support',
    patterns: [
      'bug', 'crash', 'doesn\'t work', 'error message',
      'technical issue', 'not loading', 'can\'t access', 'login problem'
    ],
    responses: [
      'Let me help you troubleshoot. Have you tried:\n1. Refreshing the page\n2. Clearing your browser cache\n3. Trying a different browser\n\nWhich step would you like to try first?',
      'I can help with that. Could you tell me:\n• What error message you\'re seeing?\n• When did this start?\n• What browser are you using?'
    ],
    priority: 'high',
    category: 'support'
  },
  {
    intent: 'thank_you',
    patterns: [
      'thanks', 'thank you', 'appreciate it', 'helpful',
      'great', 'awesome', 'perfect', 'excellent'
    ],
    responses: [
      'You\'re welcome! Happy to help! 😊',
      'Glad I could assist! Is there anything else you need?',
      'My pleasure! Feel free to reach out anytime.',
      'You\'re very welcome! Don\'t hesitate to ask if you need more help.'
    ],
    priority: 'medium',
    category: 'general'
  },
  {
    intent: 'goodbye',
    patterns: [
      'bye', 'goodbye', 'see you', 'talk later', 'thanks bye',
      'that\'s all', 'nothing else', 'done', 'exit'
    ],
    responses: [
      'Goodbye! Feel free to return if you need anything else. 👋',
      'Thanks for chatting! Have a great day!',
      'See you later! Don\'t hesitate to reach out again.',
      'Take care! We\'re here whenever you need us.'
    ],
    priority: 'medium',
    category: 'general'
  },
  {
    intent: 'demo_request',
    patterns: [
      'demo', 'trial', 'test', 'try it out', 'see it in action',
      'show me', 'demonstration', 'preview'
    ],
    responses: [
      'Great! I can set up a demo for you. Please provide:\n• Your name\n• Company name\n• Email address\n• Preferred date/time\n\nOr would you like to schedule it directly?',
      'I\'d love to show you our platform! Would you prefer:\n1. Live demo with our team\n2. Self-guided tour\n3. Video walkthrough'
    ],
    priority: 'high',
    category: 'sales'
  },
  {
    intent: 'fallback',
    patterns: ['*'],
    responses: [
      'I\'m not sure I understand. Could you rephrase that?',
      'Hmm, I didn\'t quite get that. Can you try asking differently?',
      'I\'m still learning! Can you be more specific?',
      'I\'m not sure how to help with that. Could you provide more details?'
    ],
    priority: 'low',
    category: 'general'
  }
]
