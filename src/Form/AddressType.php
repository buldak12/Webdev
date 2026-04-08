<?php

namespace App\Form;

use App\Entity\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Full Name',
                'attr' => ['placeholder' => 'Juan Dela Cruz'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter your full name']),
                    new Assert\Length(['min' => 2, 'max' => 100]),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Phone Number',
                'attr' => ['placeholder' => '09XX XXX XXXX'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter your phone number']),
                    new Assert\Regex([
                        'pattern' => '/^(09|\+639)\d{9}$/',
                        'message' => 'Please enter a valid Philippine mobile number',
                    ]),
                ],
            ])
            ->add('streetAddress', TextType::class, [
                'label' => 'Street Address',
                'attr' => [
                    'placeholder' => 'House/Unit No., Street Name, Building',
                    'autocomplete' => 'off',
                    'data-address-field' => 'streetAddress',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter your street address']),
                    new Assert\Length(['max' => 255]),
                ],
            ])
            ->add('barangay', TextType::class, [
                'label' => 'Barangay',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Barangay name',
                    'data-address-field' => 'barangay',
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'City/Municipality',
                'attr' => [
                    'placeholder' => 'Makati City',
                    'data-address-field' => 'city',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter your city']),
                    new Assert\Length(['max' => 100]),
                ],
            ])
            ->add('province', TextType::class, [
                'label' => 'Province',
                'attr' => [
                    'placeholder' => 'Metro Manila',
                    'data-address-field' => 'province',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter your province']),
                    new Assert\Length(['max' => 100]),
                ],
            ])
            ->add('region', ChoiceType::class, [
                'label' => 'Region',
                'choices' => array_flip(Address::REGIONS),
                'placeholder' => 'Select region',
                'attr' => [
                    'data-address-field' => 'region',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please select your region']),
                ],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Postal Code',
                'attr' => [
                    'placeholder' => '1200',
                    'data-address-field' => 'postalCode',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter your postal code']),
                    new Assert\Regex([
                        'pattern' => '/^\d{4}$/',
                        'message' => 'Please enter a valid 4-digit postal code',
                    ]),
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Delivery Notes (Optional)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Landmark, gate color, delivery instructions...',
                    'rows' => 2,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
        ]);
    }
}
