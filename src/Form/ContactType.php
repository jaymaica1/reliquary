<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'contact.form.name',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter your name',
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Your name must be at least {{ limit }} characters long',
                        'maxMessage' => 'Your name cannot be longer than {{ limit }} characters',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'contact.form.email',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter your email address',
                    ]),
                    new Assert\Email([
                        'message' => 'Please enter a valid email address',
                    ]),
                ],
            ])
            ->add('subject', ChoiceType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'contact.form.subject',
                'placeholder' => 'contact.form.select_subject',
                'choices' => [
                    'contact.form.subjects.relic_submission' => 'relic_submission',
                    'contact.form.subjects.authentication' => 'authentication',
                    'contact.form.subjects.general_inquiry' => 'general_inquiry',
                    'contact.form.subjects.technical_support' => 'technical_support',
                    'contact.form.subjects.partnership' => 'partnership',
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please select a subject',
                    ]),
                ],
            ])
            ->add('message', TextareaType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 6,
                ],
                'label' => 'contact.form.message',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter your message',
                    ]),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 5000,
                        'minMessage' => 'Your message must be at least {{ limit }} characters long',
                        'maxMessage' => 'Your message cannot be longer than {{ limit }} characters',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \App\Entity\Contact::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'contact_form',
            'translation_domain' => 'landing',
        ]);
    }
}
