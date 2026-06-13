<?php

namespace App\Form;

use App\Entity\Salle;
use App\Entity\ResponsableSalle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Validator\Constraints as Assert;

class SalleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentSalleId = $options['current_salle_id'] ?? null;

        $builder
            ->add('nom', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom de la salle est requis.']),
                    new Assert\Length([
                        'max' => 200,
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('adresse', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L’adresse est requise.']),
                    new Assert\Length([
                        'max' => 200,
                        'maxMessage' => 'L’adresse ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('num_tel', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le numéro de téléphone est requis.']),
                    new Assert\Regex([
                        'pattern' => '/^\+216\s\d{2}\s\d{3}\s\d{3}$/',
                        'message' => 'Le format doit être: +216 XX XXX XXX',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('email', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L’email est requis.']),
                    new Assert\Email(['message' => 'L’email n’est pas valide.']),
                    new Assert\Length([
                        'max' => 100,
                        'maxMessage' => 'L’email ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('details', TextareaType::class, [
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Les détails sont requis.']),
                    new Assert\Length([
                        'max' => 500,
                        'maxMessage' => 'Les détails ne peuvent pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('image', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, WebP).',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('responsable', EntityType::class, [
                'class' => ResponsableSalle::class,
                'choice_label' => function (ResponsableSalle $responsable) {
                    return sprintf('%s %s (%s)', $responsable->getNom(), $responsable->getPrenom(), $responsable->getEmail());
                },
                'query_builder' => function (EntityRepository $er) use ($currentSalleId) {
                    $qb = $er->createQueryBuilder('r')
                             ->leftJoin('r.salle', 's')
                             ->where('s.id IS NULL');
                    if ($currentSalleId) {
                        $qb->orWhere('s.id = :salleId')
                           ->setParameter('salleId', $currentSalleId);
                    }
                    return $qb;
                },
                'placeholder' => 'Sélectionner un responsable',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le responsable est requis.']),
                ],
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Salle::class,
            'current_salle_id' => null,
        ]);

        $resolver->setAllowedTypes('current_salle_id', ['null', 'int']);
    }
}