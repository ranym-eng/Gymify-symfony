<?php

namespace App\Form;

use App\Entity\Equipe;
use App\Enum\Niveau;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class EquipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Team Name',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Team name is required.']),
                    new Assert\Length(['max' => 255, 'maxMessage' => 'Team name cannot exceed 255 characters.']),
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Team Image',
                'mapped' => false,
                'required' => false, // Make image optional for edits
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'maxSizeMessage' => 'Image must be less than 2MB.',
                        'mimeTypesMessage' => 'Please upload a valid image (JPG, PNG, or WEBP).',
                    ]),
                ],
            ])
            ->add('niveau', EnumType::class, [
                'class' => Niveau::class,
                'choice_label' => fn(Niveau $niveau) => ucfirst(strtolower($niveau->value)),
                'constraints' => [
                    new Assert\NotNull(['message' => 'Level is required.']),
                ],
            ])
            ->add('nombre_membres', IntegerType::class, [
                'label' => 'Number of Members',
                'attr' => ['min' => 0, 'max' => 8],
                'constraints' => [
                    new Assert\NotNull(['message' => 'Number of members is required.']),
                    new Assert\GreaterThanOrEqual(['value' => 0, 'message' => 'Number of members must be 0 or greater.']),
                    new Assert\LessThanOrEqual(['value' => 8, 'message' => 'Number of members cannot exceed 8.']),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save Team',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipe::class,
        ]);
    }
}