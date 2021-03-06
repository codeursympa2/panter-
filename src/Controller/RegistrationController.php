<?php

namespace App\Controller;

use App\Anrchi\EmailSendA;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use App\Security\LoginFormAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    private $emailVerifier;
    
    public function __construct(EmailVerifier $emailVerifier)
    {
        $this->emailVerifier = $emailVerifier;
    }

    /**
     * @Route("/register", name="app_register")
     */
    public function register(Request $request,MailerInterface $mailer,EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder, GuardAuthenticatorHandler $guardHandler, LoginFormAuthenticator $authenticator): Response
    {
        
        if ($this->getUser()) {
            $this->addFlash('error','Vous avez dejà un compte');
            return $this->redirectToRoute('home');
        }
        /** 
        #envoyer un email sous symfony
        $email = (new Email())
            ->from('hello@example.com')
            ->to('you@example.com')
            //->cc('cc@example.com')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            //->priority(Email::PRIORITY_HIGH)
            ->subject('Time for Symfony Mailer!')
            ->text('Sending emails is fun again!')
            ->html('<p>See Twig integration for better HTML integration!</p>');

        $mailer->send($email);*/
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            //
            $user->setPassword(
                $passwordEncoder->encodePassword(
                    $user,
                    $form['plainPassword']->getData()
                )
            );
            //dd($form->getData());
            $em->persist($user);
            $em->flush();
            EmailSendA::sendAnrchi($user,$this->emailVerifier);
            // generate a signed url and email it to the user
            // do anything else you need here, like send an email
            $request->getSession()->set('email','ok');
            //autehtification de l'utilisateur
            return $guardHandler->authenticateUserAndHandleSuccess(
                $user,
                $request,
                $authenticator,
                'main' // firewall name in security.yaml
            );
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/verify/email", name="app_verify_email")
     */
    public function verifyUserEmail(Request $request): Response
    {
        //Pour verifier un email il faut qu'il soit connécté
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $this->getUser());
        } catch (VerifyEmailExceptionInterface $exception) {
            $request->getSession()->set('email','ok');
            $this->addFlash('error', 'Le lien pour vérifier votre e-mail n’est pas valide. Veuillez demander un nouveau lien.');

            return $this->redirectToRoute('home');
        }
        $this->get('session')->clear();
        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Votre email a été verifié avec succès.');

        return $this->redirectToRoute('home');
    }

    /**
     * @Route("renvoie/email",name="app_redirect_email")
     *
     */
    public function renvoieEmail():Response
    {   /** 
        $email=$this->get('session')->get('email');
        $user=new User;
        $userEmail=$user->setEmail($email);
        */
        $userEmail=$this->getUser();
        EmailSendA::sendAnrchi($userEmail,$this->emailVerifier);
        $this->addFlash(
           'success',
           'Un autre email de confirmation a été envoyé à votre adresse email. Merci de vérifier.'
        );
        $this->get('session')->clear();
        return $this->redirectToRoute('home');
    }
}
