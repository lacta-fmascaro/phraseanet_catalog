<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\CreateUserFormType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Class UserController
 * @package App\Controller
 */
class UserController extends AbstractController
{

    /**
     * @Route("/users", name="app_users")
     */
    public function usersListAction(Request $request, UserPasswordEncoderInterface $encoder, UserRepository $userRepository)
    {
        $usersList = [];
        $usersList = $this->getDoctrine()->getRepository(User::class)->findAll();

        $user = new User();
        $registrationForm = $this->createForm(CreateUserFormType::class, $user);

        $registrationForm->handleRequest($request);

        if ($registrationForm->isSubmitted() && $registrationForm->isValid()) {

            $exist = $userRepository->findOneBy(['email' => $user->getEmail()]);

            if ($exist instanceof User) {
                $this->addFlash('error', 'The user ' . $user->getEmail() . ' already exist');

                return $this->redirectToRoute('app_users');
            }

            $user->setRoles(["ROLE_USER"]);

            $password = $encoder->encodePassword($user, $registrationForm->get('plain_password')->getData());
            $user->setPassword($password);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'The user ' . $user->getEmail() . ' has been created');

            return $this->redirectToRoute('app_users');

        }

        return $this->render('users/list.html.twig', [
            'usersList' => $usersList,
            'registrationForm' => $registrationForm->createView(),
        ]);
    }

    /**
     * @Route("/user/update/{id}", name="app_users_update", requirements={"id"="\d+"})
     */
    public function updateUserAction(Request $request, $id, UserRepository $userRepository, UserPasswordEncoderInterface $encoder)
    {
        $user = $userRepository->find($id);

        if (!$user) {
            $this->addFlash('error', "The user " . $id . " doesn't exist");

            return $this->redirectToRoute('app_users');
        }

        $form = $this->createForm(CreateUserFormType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($form->get('plain_password')->getData() != '') {
                $password = $encoder->encodePassword($user, $form->get('plain_password')->getData());
                $user->setPassword($password);
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'The user ' . $user->getEmail() . ' has been updated');

            return $this->redirectToRoute('app_users');
        }

        return $this->render('users/update.html.twig', [
            'form' => $form->createView(),
        ]);

    }
}
