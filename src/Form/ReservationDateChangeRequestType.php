<?php

namespace App\Form;

use App\Entity\ReservationDateChangeRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationDateChangeRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $builder
            ->add('newStartDate', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'label' => 'Nouvelle date de début',
                'attr' => [
                    'min' => $today,
                    'class' => 'field__input',
                ],
            ])
            ->add('newEndDate', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'label' => 'Nouvelle date de fin',
                'attr' => [
                    'min' => $today,
                    'class' => 'field__input',
                ],
            ])
            ->add('reason', TextareaType::class, [
                'required' => false,
                'label' => 'Motif (optionnel)',
                'attr' => [
                    'rows' => 4,
                    'class' => 'field__input',
                    'placeholder' => 'Explique brièvement pourquoi tu souhaites changer les dates…',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationDateChangeRequest::class,
        ]);
    }
}
