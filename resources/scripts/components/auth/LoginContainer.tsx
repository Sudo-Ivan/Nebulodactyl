import type { FormikHelpers } from 'formik';
import { Formik } from 'formik';
import { useNavigate } from 'react-router-dom';
import { object, string } from 'yup';
import login from '@/api/auth/login';
import LoginFormContainer, { TitleSection } from '@/components/auth/LoginFormContainer';
import Button from '@/components/elements/Button';
import Captcha, { getCaptchaResponse } from '@/components/elements/Captcha';
import Field from '@/components/elements/Field';

import CaptchaManager from '@/lib/captcha';
import useFlash from '@/plugins/useFlash';
import type { SiteSettings } from '@/state/settings';

import SecondaryLink from '../ui/secondary-link';

interface Values {
    user: string;
    password: string;
}

interface ErrorResponse {
    response: string;
    message: string;
    detail: string;
    code: string;
}

function LoginContainer() {
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const navigate = useNavigate();
    const oidc = (window as unknown as { SiteConfiguration?: SiteSettings }).SiteConfiguration?.oidc;

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        // clearFlashes();

        let loginData: Values = values;
        if (CaptchaManager.isEnabled()) {
            const captchaResponse = getCaptchaResponse();
            const fieldName = CaptchaManager.getProviderInstance().getResponseFieldName();

            if (fieldName) {
                if (captchaResponse) {
                    loginData = { ...values, [fieldName]: captchaResponse };
                } else {
                    console.error('Captcha enabled but no response available');
                    clearAndAddHttpError({
                        error: new Error('Please complete the captcha verification.'),
                    });
                    setSubmitting(false);
                    return;
                }
            }
        } else {
            // No captcha required
        }

        login(loginData)
            .then((response) => {
                if (response.complete) {
                    clearFlashes();
                    window.location.href = response.intended || '/';
                    return;
                }
                navigate('/auth/login/checkpoint', {
                    state: { token: response.confirmationToken },
                });
            })
            .catch((error: ErrorResponse) => {
                setSubmitting(false);

                if (error.code === 'InvalidCredentials') {
                    clearAndAddHttpError({
                        error: new Error('Invalid username or password. Please try again.'),
                    });
                } else if (error.code === 'DisplayException') {
                    clearAndAddHttpError({
                        error: new Error(error.detail || error.message),
                    });
                } else {
                    clearAndAddHttpError({ error });
                }
            });
    };

    return (
        <Formik
            onSubmit={onSubmit}
            initialValues={{ user: '', password: '' }}
            validationSchema={object().shape({
                user: string().required('A username or email must be provided.'),
                password: string().required('Please enter your account password.'),
            })}
        >
            {({ isSubmitting }) => (
                <LoginFormContainer className='mx-auto flex w-full max-w-md flex-col gap-6 rounded-2xl border border-cream-500/10 bg-bg-raised p-8 shadow-xl shadow-black/40'>
                    <TitleSection title='Login' />
                    <div className=''>
                        <Field
                            id='user'
                            type={'text'}
                            label={'Username or Email'}
                            name={'user'}
                            disabled={isSubmitting}
                        />
                    </div>

                    <div className={`relative mt-6`}>
                        <Field
                            id='password'
                            type={'password'}
                            label={'Password'}
                            name={'password'}
                            disabled={isSubmitting}
                        />
                    </div>

                    <Captcha
                        className='mt-6'
                        onError={(error) => {
                            console.error('Captcha error:', error);
                            clearAndAddHttpError({
                                error: new Error('Captcha verification failed. Please try again.'),
                            });
                        }}
                    />

                    <div className='flex w-full flex-col gap-4 sm:flex-row sm:justify-between sm:items-center'>
                        <Button
                            className={`bg-cream-400 rounded-lg p-2 px-4 text-mocha-500 hover:cursor-pointer hover:bg-cream-300 ease-in-out w-full sm:w-auto`}
                            type={'submit'}
                            size={'xlarge'}
                            isLoading={isSubmitting}
                            disabled={isSubmitting}
                        >
                            Sign in
                        </Button>
                        <SecondaryLink to='/auth/password' className='text-center sm:text-right'>
                            Forgot your password?
                        </SecondaryLink>
                    </div>

                    {oidc?.enabled && (
                        <div className='mt-6'>
                            <div className='flex items-center gap-3 mb-4'>
                                <div className='h-px flex-1 bg-cream-50/10' />
                                <span className='text-xs text-secondary uppercase tracking-wide'>or</span>
                                <div className='h-px flex-1 bg-cream-50/10' />
                            </div>
                            <a
                                href='/auth/oidc'
                                className='block w-full text-center rounded-lg border border-cream-50/10 bg-cream-50/5 p-2 px-4 text-sm font-bold text-cream-100 hover:bg-cream-50/10 transition-colors no-underline'
                            >
                                Continue with {oidc.displayName || 'SSO'}
                            </a>
                        </div>
                    )}
                </LoginFormContainer>
            )}
        </Formik>
    );
}

export default LoginContainer;
