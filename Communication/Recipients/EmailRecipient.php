<?php
namespace exface\Core\Communication\Recipients;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Communication\EmailRecipientInterface;

class EmailRecipient implements EmailRecipientInterface
{
    private $email = null;
    
    /**
     * 
     * @param UserInterface $user
     */
    public function __construct(string $emailAddress)
    {
        $this->email = $emailAddress;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\EmailRecipientInterface::getEmail()
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }
}