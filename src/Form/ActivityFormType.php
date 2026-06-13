<?php
namespace App\Form;

use App\Entity\Activité;
use App\Enum\ActivityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class ActivityFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Activity Name *',
                'constraints' => [
                    new NotBlank(['message' => 'Please enter an activity name']),
                    new Length([
                        'max' => 50,
                        'maxMessage' => 'Name must be less than {{ limit }} characters'
                    ])
                ],
                'attr' => [
                    'data-maxlength' => 50
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description *',
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a description']),
                    new Length([
                        'max' => 300,
                        'maxMessage' => 'Description must be less than {{ limit }} characters'
                    ])
                ],
                'attr' => [
                    'rows' => 4,
                    'data-maxlength' => 300
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Activity Type *',
                'choices' => ActivityType::cases(),
                'choice_label' => function(ActivityType $type) {
                    return $type->label();
                },
                'choice_value' => 'value',
                'expanded' => true,
                'multiple' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Please select an activity type'])
                ]
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Activity Image *',
                'mapped' => false,
                'required' => true,
                
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activité::class,
        ]);
    }
}