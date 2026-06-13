<?php

namespace App\Form;

use App\Entity\Post;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
         $builder
             ->add('title', TextType::class, [
                 'label' => 'Titre du post',
                 'attr' => [
                     'required'                       => 'required',
                     'data-parsley-minlength'         => '3',
                     'data-parsley-minlength-message' => 'Le titre doit contenir au moins 3 caractères.',
                     'data-parsley-maxlength'         => '100',
                     'data-parsley-maxlength-message' => 'Le titre ne peut pas dépasser 100 caractères.',
                     'data-parsley-pattern'           => '^(?!.*\b(spam|arnaque|insulte)\b).*$',
                     'data-parsley-trigger'           => 'keyup'
                 ],
             ])
             ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => [
                    'data-parsley-clean-content' => 'true',
                    'data-parsley-forbidden-words' => 'true'
                ]
            ])
             ->add('webImage', UrlType::class, [
                'label'         => "URL de l'image (optionnel)",
                'required'      => false,
                'mapped'        => false,  
                'property_path' => null,   
                'attr'          => [
                    'class'       => 'form-control',
                    'placeholder' => "Entrez l'URL de l'image si vous préférez",
                    'type'        => 'url',
                    'data-parsley-type' => 'url',
                    'data-parsley-type-message' => "Veuillez entrer une URL valide."
                ],
             ])
             ->add('imageFile', FileType::class, [
                 'label'    => 'Image locale (fichier)',
                 'mapped'   => false,
                 'required' => false,
                 'constraints' => [
                     new File([
                         'maxSize'          => '5M',
                         'mimeTypes'        => [
                             'image/jpeg',
                             'image/png',
                             'image/jpg',
                         ],
                         'mimeTypesMessage' => 'Veuillez uploader un fichier image valide (jpg ou png)',
                     ])
                 ],
                 'attr' => ['class' => 'form-control']
             ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
         $resolver->setDefaults([
             'data_class' => Post::class,
         ]);
    }
}