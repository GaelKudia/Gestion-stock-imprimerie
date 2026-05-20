<?php

namespace App\Form;

use App\Entity\Visite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VisiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomVisiteur', TextType::class, [
                'label' => 'Nom du visiteur',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Jean Dupont']
            ])
            ->add('institutionHote', ChoiceType::class, [
                'label' => 'Qui reçoit le visiteur ?',
                'choices' => [
                    'Directeur' => 'Directeur',
                    'Coordinateur' => 'Coordinateur',
                    'Secrétaire PDG' => 'Secrétaire PDG',
                    'Département' => 'Département',
                ],
                'attr' => ['class' => 'form-select']
            ])
            // MODIFICATION ICI : On utilise le type natif HTML5 éclaté (Date + Heure séparées)
            ->add('heureRdv', DateTimeType::class, [
                'label' => 'Date et Heure du RDV',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('motif', TextareaType::class, [
                'label' => 'Motif de la visite (Optionnel)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Ex: Entretien professionnel']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Visite::class,
        ]);
    }
}