<?php

namespace App\Form;

use App\Entity\Events;
use App\Enum\EventType;
use App\Enum\Reward;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class EventsType extends AbstractType
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Event Name',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Event name is required.']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 255,
                        'minMessage' => 'Event name must be at least 2 characters long.',
                        'maxMessage' => 'Event name cannot exceed 255 characters.',
                    ]),
                    new Assert\Callback(function ($value, ExecutionContextInterface $context) use ($isEdit) {
                        if (!$value) {
                            return;
                        }
                        $event = $context->getObject()->getParent()->getData();
                        $existingEvent = $this->entityManager->getRepository(Events::class)
                            ->findOneBy(['nom' => $value]);
                        if ($existingEvent && (!$isEdit || $existingEvent->getId() !== $event->getId())) {
                            $context->buildViolation('Event name already exists.')
                                ->atPath('nom')
                                ->addViolation();
                        }
                    }),
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Event Image',
                'mapped' => false,
                'required' => !$isEdit,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'maxSizeMessage' => 'Image must be less than 2MB.',
                        'mimeTypesMessage' => 'Please upload a valid image (JPG, PNG, or WEBP).',
                    ]),
                ],
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'attr' => [
                    'type' => 'date',
                    'min' => (new \DateTime('today'))->format('Y-m-d'),
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Date is required.']),
                    new Assert\GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'The date must be today or in the future.',
                    ]),
                ],
            ])
            ->add('heure_debut', TimeType::class, [
                'widget' => 'single_text',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Start time is required.']),
                ],
            ])
            ->add('heure_fin', TimeType::class, [
                'widget' => 'single_text',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'End time is required.']),
                    new Assert\Callback(function ($value, ExecutionContextInterface $context) {
                        $form = $context->getObject()->getParent()->getData();
                        $startTime = $form->getHeureDebut();
                        if ($startTime && $value) {
                            if ($value <= $startTime) {
                                $context->buildViolation('End time must be after start time.')
                                    ->atPath('heure_fin')
                                    ->addViolation();
                            }
                        }
                    }),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => EventType::class,
                'choice_label' => fn(EventType $type) => ucfirst($type->value),
                'placeholder' => 'Select event type',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Event type is required.']),
                ],
            ])
            ->add('reward', EnumType::class, [
                'class' => Reward::class,
                'choice_label' => fn(Reward $reward) => ucfirst($reward->value),
                'placeholder' => 'Select reward',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Reward is required.']),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Description is required.']),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 500,
                        'minMessage' => 'Description must be at least 10 characters long.',
                        'maxMessage' => 'Description cannot exceed 500 characters.',
                    ]),
                ],
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Location',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Location is required.']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 255,
                        'minMessage' => 'Location must be at least 2 characters long.',
                        'maxMessage' => 'Location cannot exceed 255 characters.',
                    ]),
                ],
            ])
            ->add('latitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('longitude', HiddenType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Events::class,
            'is_edit' => false,
        ]);
    }
}