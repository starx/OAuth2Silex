<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */
namespace OAuth2ServerExamples\Forms\OAuth2;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class SignInFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username')
            ->add('password', PasswordType::class)
            ->add('submit', SubmitType::class, [
                'label' => 'Sign in',
            ])
        ;
    }

}