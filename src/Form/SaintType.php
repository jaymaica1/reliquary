<?php

namespace App\Form;

use App\Entity\Saint;
use App\Enum\CanonicalStatus;
use Doctrine\DBAL\Types\StringType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class SaintType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'saint.form.name',
                'translation_domain' => 'saint',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'saint.form.name_placeholder',
                ],
                'help' => 'saint.form.name_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('url', null, [
                'label' => 'saint.form.url',
                'translation_domain' => 'saint',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'saint.form.url_placeholder',
                ],
                'help' => 'saint.form.url_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('file', null, [
                'label' => 'saint.form.file',
                'translation_domain' => 'saint',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'saint.form.file_placeholder',
                ],
                'help' => 'saint.form.file_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('canonical_status', EnumType::class, [
                'label' => 'saint.form.canonical_status',
                'translation_domain' => 'saint',
                'class' => CanonicalStatus::class,
                'choice_label' => fn(CanonicalStatus $status) => $status->getLabel(),
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'saint.form.canonical_status_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
                'required' => false,
            ])
            ->add('canonization_date', DateType::class, [
                'label' => 'saint.form.canonization_date',
                'translation_domain' => 'saint',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'saint.form.canonization_date_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
                'required' => false,
            ])
            ->add('feast_date', DateType::class, [
                'label' => 'saint.form.feast_date',
                'translation_domain' => 'saint',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'saint.form.feast_date_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
                'required' => false,
            ])
            ->add('canonizing_pope', null, [
                'label' => 'saint.form.canonizing_pope',
                'translation_domain' => 'saint',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'saint.form.canonizing_pope_placeholder',
                ],
                'help' => 'saint.form.canonizing_pope_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('saint_phrase', null, [
                'label' => 'saint.form.saint_phrase',
                'translation_domain' => 'saint',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'saint.form.saint_phrase_placeholder',
                ],
                'help' => 'saint.form.saint_phrase_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('abstract', null, [
                'label' => 'saint.form.abstract',
                'translation_domain' => 'saint',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'saint.form.abstract_placeholder',
                ],
                'help' => 'saint.form.abstract_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('biography', null, [
                'label' => 'saint.form.biography',
                'translation_domain' => 'saint',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'saint.form.biography_placeholder',
                ],
                'help' => 'saint.form.biography_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('image_link', null, [
                'label' => 'saint.form.image_link',
                'translation_domain' => 'saint',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'saint.form.image_link_placeholder',
                ],
                'help' => 'saint.form.image_link_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'saint.form.image',
                'translation_domain' => 'saint',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'saint.form.image_error',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'saint.form.image_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Saint::class,
        ]);
    }
}
