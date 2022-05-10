<?php

namespace App\Form;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class CreateUserFormType extends AbstractType
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder->add('email', EmailType::class, [
            'constraints' => [
                new NotBlank([
                    'message' => 'Please enter a valid email',
                ]),
                new Email()
            ],
        ])->add('plain_password', PasswordType::class, array(
            'label' => 'Password',
            'mapped' => false,
            'required' => false
        ))
            ->add('enabled', CheckboxType::class, [
                'label' => 'Is enabled ?',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, ['label' => 'Add']);;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
