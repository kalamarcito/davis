<?php

namespace App\Form;

use App\Entity\AddressBookInstance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddressBookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('uri', TextType::class, [
                'label' => 'form.uri',
                'disabled' => !$options['new'],
                'help' => 'form.uri.help.carddav',
                'required' => false, // Will be auto-generated if not provided
            ])
            ->add('displayName', TextType::class, [
                'label' => 'form.displayName',
                'help' => 'form.name.help.carddav',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'form.description',
                'required' => false,
            ]);

        if ($options['birthday_calendar_enabled']) {
            $builder->add('includedInBirthdayCalendar', ChoiceType::class, [
                'label' => 'form.includedInBirthdayCalendar',
                'help' => 'form.includedInBirthdayCalendar.help',
                'required' => true,
                'choices' => ['yes' => true, 'no' => false],
                'mapped' => false, // This field is handled specially in the controller
            ]);
        }

        $builder->add('save', SubmitType::class, [
            'label' => 'save',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'new' => false,
            'data_class' => AddressBookInstance::class,
            'birthday_calendar_enabled' => true,
        ]);
    }
}