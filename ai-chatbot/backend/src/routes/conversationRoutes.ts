import { Router } from 'express'
import { ConversationController } from '../controllers/ConversationController'

const router = Router()
const controller = new ConversationController()

router.post('/', controller.startConversation)
router.post('/:conversationId/messages', controller.sendMessage)
router.get('/:conversationId/messages', controller.getMessages)
router.post('/:conversationId/end', controller.endConversation)

export default router
