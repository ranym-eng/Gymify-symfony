<?php

namespace App\Form;

use App\Entity\Activite;
use App\Entity\Salle;
use App\Enum\ObjectifCours;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints as Assert;

class CoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $planning = $options['planning'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du cours *',
                'attr' => [
                    'placeholder' => 'Ex: Séance cardio avancée',
                    'maxlength' => 50,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Un titre est requis']),
                    new Assert\Length(['max' => 50, 'maxMessage' => 'Le titre ne peut pas dépasser 50 caractères'])
                ]
            ])
            ->add('objectif', ChoiceType::class, [
                'label' => 'Objectif principal *',
                'choices' => array_combine(
                    array_map(fn($case) => $case->label(), ObjectifCours::cases()),
                    array_map(fn($case) => $case->value, ObjectifCours::cases())
                ),
                'attr' => ['class' => 'form-select d-none', 'id' => 'selected-objective'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez sélectionner un objectif'])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée *',
                'attr' => [
                    'placeholder' => 'Décrivez le contenu du cours...',
                    'rows' => 4,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Une description est requise'])
                ]
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date *',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'min' => $planning->getDateDebut()->format('Y-m-d'),
                    'max' => $planning->getDateFin()->format('Y-m-d')
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La date est requise']),
                    new Assert\Date(['message' => 'Date invalide']),
                    new Assert\GreaterThanOrEqual([
                        'value' => $planning->getDateDebut(),
                        'message' => 'La date doit être après ou égale à {{ compared_value }}'
                    ]),
                    new Assert\LessThanOrEqual([
                        'value' => $planning->getDateFin(),
                        'message' => 'La date doit être avant ou égale à {{ compared_value }}'
                    ])
                ]
            ])
            ->add('heurDebut', TimeType::class, [
                'label' => 'Heure de début *',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Heure de début requise'])
                ]
            ])
            ->add('heurFin', TimeType::class, [
                'label' => 'Heure de fin *',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Heure de fin requise']),
                    new Assert\GreaterThan([
                        'propertyPath' => 'parent.all[heurDebut].data',
                        'message' => 'L\'heure de fin doit être après l\'heure de début'
                    ])
                ]
            ])
            ->add('activite', EntityType::class, [
                'label' => 'Activité *',
                'class' => Activite::class,
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionnez...',
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Sélectionnez une activité'])
                ]
            ])
            ->add('salle', EntityType::class, [
                'label' => 'Salle *',
                'class' => Salle::class,
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionnez...',
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Sélectionnez une salle'])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('planning');
    }
}