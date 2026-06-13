<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PostFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('search', SearchType::class, [
                'label' => 'Rechercher',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Titre ou contenu...',
                    'class' => 'form-control'
                ]
            ])
            ->add('author', EntityType::class, [
                'label' => 'Auteur',
                'class' => User::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => 'Tous les auteurs',
                'attr' => ['class' => 'form-select']
            ])
            ->add('itemsPerPage', ChoiceType::class, [
                'label' => 'Articles par page',
                'required' => false,
                'choices' => [
                    '4 articles' => 4,
                    '8 articles' => 8,
                    '12 articles' => 12,
                    '20 articles' => 20,
                ],
                'data' => 4,
                'attr' => ['class' => 'form-select']
            ])
            ->add('dateFrom', DateType::class, [
                'label' => 'Date (de)',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'Date (à)',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('minReactions', IntegerType::class, [
                'label' => 'Nombre min. de réactions',
                'required' => false,
                'attr' => [
                    'placeholder' => '0',
                    'min' => 0,
                    'class' => 'form-control'
                ]
            ])
            ->add('minComments', IntegerType::class, [
                'label' => 'Nombre min. de commentaires',
                'required' => false,
                'attr' => [
                    'placeholder' => '0',
                    'min' => 0,
                    'class' => 'form-control'
                ]
            ])
            ->add('sortBy', ChoiceType::class, [
                'label' => 'Trier par',
                'required' => false,
                'choices' => [
                    'Date (plus récent)' => 'date_desc',
                    'Date (plus ancien)' => 'date_asc',
                    'Nombre de réactions' => 'reactions',
                    'Nombre de commentaires' => 'comments',
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('filter', SubmitType::class, [
                'label' => 'Filtrer',
                'attr' => ['class' => 'btn btn-primary w-100 mt-3']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
} 