<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity;
use App\Exception\ValidationException;
use App\Http\Response;
use App\Http\ServerRequest;
use App\Service\Mail;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SendTestMessageAction
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly Mail $mail,
    ) {
    }

    public function __invoke(
        ServerRequest $request,
        Response $response
    ): ResponseInterface {
        $emailAddress = $request->getParam('email', '');

        $errors = $this->validator->validate(
            $emailAddress,
            [
                new Required(),
                new Email(),
            ]
        );
        if (count($errors) > 0) {
            throw ValidationException::fromValidationErrors($errors);
        }

        try {
            $email = $this->mail->createMessage();
            $email->to($emailAddress);
            $email->subject(
                __('Test Message')
            );
            $email->text(
                __(
                    'This is a test message from AmbientCast. If you are receiving this message, it means your '
                    . 'e-mail settings are configured correctly.'
                )
            );

            $this->mail->send($email);
        } catch (TransportException $e) {
            return $response->withStatus(400)->withJson(Entity\Api\Error::fromException($e));
        }

        return $response->withJson(
            new Entity\Api\Status(
                true,
                __('Test message sent successfully.')
            )
        );
    }
}