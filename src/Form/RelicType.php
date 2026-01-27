<?php

namespace App\Form;

use App\Entity\Relic;
use App\Enum\RelicDegree;
use App\EventListener\SaintFormListener;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Contracts\Translation\TranslatorInterface;

class RelicType extends AbstractType
{
    private TranslatorInterface $translator;
    private SaintFormListener $saintFormListener;

    public function __construct(TranslatorInterface $translator, SaintFormListener $saintFormListener)
    {
        $this->translator = $translator;
        $this->saintFormListener = $saintFormListener;
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->addEventListener(FormEvents::PRE_SUBMIT, [$this->saintFormListener, 'onPreSubmit'])
            ->add('address', AddressAutocompleteType::class, [
                'label' => 'relic.form.address',
                'translation_domain' => 'relic',
                'help' => 'relic.form.address_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
                'required' => true,
            ])
            ->add('location', null, [
                'label' => 'relic.form.location',
                'translation_domain' => 'relic',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'relic.form.location_placeholder',
                ],
                'help' => 'relic.form.location_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('description', RelicDescriptionAutocompleteType::class, [
                'label' => 'relic.form.description',
                'translation_domain' => 'relic',
                'help' => 'relic.form.description_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
                'required' => false,
            ])
            ->add('provenance', null, [
                'label' => 'relic.form.provenance',
                'translation_domain' => 'relic',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'relic.form.provenance_placeholder',
                ],
                'help' => 'relic.form.provenance_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
                'required' => true,
            ])
            ->add('saint', SaintAutocompleteField::class, [
                'label' => 'relic.form.saint',
                'translation_domain' => 'relic',
                'help' => 'relic.form.saint_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
                'required' => true,
            ])
            ->add('degree', EnumType::class, [
                'label' => 'relic.form.degree',
                'translation_domain' => 'relic',
                'class' => RelicDegree::class,
                'choice_label' => function (RelicDegree $degree) {
                    return $this->translator->trans($degree->getTitleTransKey(), [], 'relic');
                },
                'help' => 'relic.form.degree_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'relic.form.image',
                'translation_domain' => 'relic',
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
                        'mimeTypesMessage' => 'relic.form.image_error',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'relic.form.image_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('pii_consent', CheckboxType::class, [
                'label' => 'relic.form.pii_consent',
                'translation_domain' => 'relic',
                'mapped' => false,
                'required' => true,
                'data' => true,
                'constraints' => [
                    new IsTrue([
                        'message' => 'relic.form.pii_consent_error',
                    ]),
                ],
                'help' => 'relic.form.pii_consent_help',
                'help_attr' => ['class' => 'form-text text-muted'],
                'label_attr' => ['class' => 'form-check-label'],
                'attr' => ['class' => 'form-check-input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Relic::class,
        ]);
    }
}
