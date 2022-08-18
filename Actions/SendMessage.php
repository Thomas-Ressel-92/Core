<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Exceptions\CommunicationExceptionInterface;
use exface\Core\Exceptions\Communication\CommunicationNotSentError;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\Traits\SendMessagesFromDataTrait;
use exface\Core\CommonLogic\UxonObject;

/**
 * Sends one or more messages through communication channels.
 * 
 * @author Andrej Kabachnik
 *
 */
class SendMessage extends AbstractAction
{
    use SendMessagesFromDataTrait;
    
    private $messageUxons = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        $dataSheet = $this->getInputDataSheet($task);
        $count = 0;
        try {
            $communicator = $this->getWorkbench()->getCommunicator();
            foreach ($this->getMessageEnvelopes(($this->messageUxons ?? new UxonObject()), $dataSheet) as $envelope) {
                $communicator->send($envelope);
                $count++;
            }
        } catch (\Throwable $e) {
            if (($e instanceof CommunicationExceptionInterface) || $envelope === null) {
                $sendingError = $e;
            } else {
                $sendingError = new CommunicationNotSentError($envelope, 'Cannot send notification: ' . $e->getMessage(), null, $e);
            }
            throw $sendingError;
        }
        
        $result = ResultFactory::createDataResult($task, $dataSheet);
        $result->setMessage($count . ' messages send');
        
        return $result;
    }
    
    /**
     * Array of messages to send - each with a separate message model: channel, recipients, etc.
     *
     * You can use the following placeholders inside any message model - as recipient,
     * message subject - anywhere:
     *
     * - `[#~config:app_alias:config_key#]` - will be replaced by the value of the `config_key` in the given app
     * - `[#~translate:app_alias:translation_key#]` - will be replaced by the translation of the `translation_key`
     * from the given app
     * - `[#~data:column_name#]` - will be replaced by the value from `column_name` of the data sheet,
     * for which the notification was triggered - only works with notification that have data sheets present!
     * - `[#=Formula()#]` - will evaluate the `Formula` (e.g. `=Now()`) in the context of the notification.
     * This means, static formulas will always work, while data-driven formulas will only work on notifications
     * that have data sheets present!
     *
     * @uxon-property messages
     * @uxon-type \exface\Core\CommonLogic\Communication\AbstractMessage
     * @uxon-template [{"channel": ""}]
     *
     * @param UxonObject $arrayOfMessages
     * @return SendMessage
     */
    public function setMessages(UxonObject $arrayOfMessages) : SendMessage
    {
        $this->messageUxons = $arrayOfMessages;
        return $this;
    }
}