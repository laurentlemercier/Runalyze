<?php

namespace Runalyze\Bundle\CoreBundle\Controller;

use Runalyze\Bundle\CoreBundle\Entity\Account;
use Runalyze\Bundle\CoreBundle\Entity\AccountRepository;
use Runalyze\Bundle\CoreBundle\Form\RecoverPasswordType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;

/**
 * @Route("/{_locale}/account")
 */
class AccountController extends Controller
{
    /**
     * @return AccountRepository
     */
    protected function getAccountRepository()
    {
        return $this->getDoctrine()->getRepository('CoreBundle:Account');
    }

    /**
     * @return AccountHashRepository
     */
    protected function getAccountHashRepository()
    {
        return $this->getDoctrine()->getRepository('CoreBundle:AccountHash');
    }


    /**
     * @Route("/delete/{hash}/confirmed", name="account_delete_confirmed", requirements={"hash": "[[:xdigit:]]{32}"})
     */
    public function deleteAccountConfirmedAction($hash, Request $request)
    {
        $accountHash = $this->getAccountHashRepository()->getAccountByDeletionHash($hash);

        if ($this->getAccountRepository()->remove($accountHash->getAccount())) {
            $this->get('security.token_storage')->setToken(null);
            $request->getSession()->invalidate();

            return $this->render('account/delete/success.html.twig');
        }

        return $this->render('account/delete/problem.html.twig');
    }

    /**
     * @Route("/delete/{hash}", name="account_delete", requirements={"hash": "[[:xdigit:]]{32}"})
     */
    public function deleteAccountAction($hash)
    {
        /** @var AccountHash|null $accountHash */
        $accountHash = $this->getAccountHashRepository()->getAccountByDeletionHash($hash);

            if (null === $accountHash->getAccount()) {
                return $this->render('account/delete/problem.html.twig');
            }

        return $this->render('account/delete/please_confirm.html.twig', [
            'deletionHash' => $hash,
            'username' => $accountHash->getAccount()->getUsername()
        ]);
    }

    /**
     * @Route("/recover/{hash}", name="account_recover_hash", requirements={"hash": "[[:xdigit:]]{32}"})
     */
    public function recoverForHashAction($hash, Request $request)
    {
        /** @var AccountHash|null $account */
        $accountHash = $this->getAccountHashRepository()->getAccountByDeletionHash($hash);

        if (null === $accountHash) {
            return $this->render('account/recover/hash_invalid.html.twig', ['recoverHash' => $hash]);
        }
        $account = $accountHash->getAccount();

        $form = $this->createForm(RecoverPasswordType::class, $account);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $encoder = $this->container->get('security.encoder_factory')->getEncoder($account);

            $account->setNewSalt();
            $account->setPassword($encoder->encodePassword($account->getPlainPassword(), $account->getSalt()));
            $account->removeChangePasswordHash();

            $this->getAccountRepository()->save($account);

            return $this->render('account/recover/success.html.twig');
        }

        return $this->render('account/recover/form_new_password.html.twig', [
            'recoverHash' => $hash,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/recover", name="account_recover")
     */
    public function recoverAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('username', TextType::class, [
                'required' => false,
                'attr' => [
                    'autofocus' => true
                ]
            ])->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            /** @var Account|null $account */
            $account = $this->getAccountRepository()->loadUserByUsername([
                $request->request->get($form->getName())['username']
            ]);

            if (null === $account) {
                $form->get('username')->addError(new FormError($this->get('translator')->trans('The username is not known.')));
            } else {
                $accountHash = $this->getAccountHashRepository()->addRecoverPasswordHash($account);

                $this->get('app.mailer.account')->sendRecoverPasswordLinkTo($account, $accountHash->getHash());

                return $this->render('account/recover/mail_delivered.html.twig');
            }
        }

        return $this->render('account/recover/form_send_mail.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/activate/{hash}", requirements={"hash": "[[:xdigit:]]{32}"})
     * @Route("/activate/{hash}/{username}", name="account_activate", requirements={"hash": "[[:xdigit:]]{32}"})
     */
    public function activateAccountAction($hash, $username = null)
    {
        /** @var AccountHash|null $accountHash */
        if ($accountHash = $this->getAccountHashRepository()->activateAccount($hash, $username)) {
            $this->getAccountRepository()->activateAccount($accountHash->getAccount());
            $this->getAccountHashRepository()->remove($accountHash);
            return $this->render('account/activate/success.html.twig');
        } elseif (null !== $username && null != $this->getAccountRepository()->loadUserByUsername($username)) {
            return $this->render('account/activate/success.html.twig', ['username' => $username, 'alreadyActivated' => true]);
        }

        return $this->render('account/activate/problem.html.twig');
    }
}
