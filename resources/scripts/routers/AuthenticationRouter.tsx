import { Route, Routes } from 'react-router-dom';

import ForgotPasswordContainer from '@/components/auth/ForgotPasswordContainer';
import LoginCheckpointContainer from '@/components/auth/LoginCheckpointContainer';
import LoginContainer from '@/components/auth/LoginContainer';
import ResetPasswordContainer from '@/components/auth/ResetPasswordContainer';
import Logo from '@/components/elements/NebulaLogo';
import { NotFound } from '@/components/elements/ScreenBlock';

import './auth-scene.css';

const getSiteName = () => {
    const siteConfiguration = (window as Record<string, unknown>).SiteConfiguration as { name?: unknown } | undefined;

    return typeof siteConfiguration?.name === 'string' && siteConfiguration.name.trim().length > 0
        ? siteConfiguration.name
        : 'Nebulodactyl';
};

const AuthScene = () => {
    const siteName = getSiteName();

    return (
        <aside className='relative hidden min-h-dvh flex-1 flex-col justify-between px-14 py-12 xl:px-20 lg:flex'>
            <div className='flex items-center gap-3'>
                <Logo className='h-9 w-9' uniqueId='auth-scene' />
                <span className='text-sm font-medium tracking-wide text-cream-300'>{siteName}</span>
            </div>
            <div className='max-w-lg'>
                <h1 className='text-4xl font-semibold tracking-tight text-cream-100 xl:text-5xl'>
                    Game servers, from one panel.
                </h1>
                <p className='mt-4 max-w-md text-sm leading-6 text-cream-400'>
                    Console, files, backups, and schedules for the machines you already run. Nodes can join over a
                    Nebula overlay and keep panel traffic on your private network.
                </p>
            </div>
            <ul className='max-w-lg list-none divide-y divide-cream-500/15 border-y border-cream-500/15 text-sm text-cream-300'>
                <li className='py-3'>Private network or overlay</li>
                <li className='py-3'>Mods and plugins from the panel</li>
                <li className='py-3'>Backups on any S3-compatible store</li>
            </ul>
        </aside>
    );
};

const AuthenticationRouter = () => {
    const siteName = getSiteName();

    return (
        <div className='absolute inset-0 flex min-h-dvh w-full'>
            <div className='auth-scene pointer-events-none absolute inset-0' aria-hidden='true' />
            <div className='relative z-2 flex min-h-dvh w-full flex-col justify-center overflow-y-auto px-6 py-10 lg:w-[32rem] lg:shrink-0 lg:border-r lg:border-cream-500/10 lg:bg-bg/92 lg:px-8'>
                <div className='mb-8 flex items-center gap-3 lg:hidden'>
                    <Logo className='h-8 w-8' uniqueId='auth-mobile' />
                    <span className='text-sm font-semibold tracking-wide text-cream-100'>{siteName}</span>
                </div>
                <Routes>
                    <Route path='login' element={<LoginContainer />} />
                    <Route path='login/checkpoint/*' element={<LoginCheckpointContainer />} />
                    <Route path='password' element={<ForgotPasswordContainer />} />
                    <Route path='password/reset/:token' element={<ResetPasswordContainer />} />
                    <Route path='*' element={<NotFound />} />
                </Routes>
            </div>
            <AuthScene />
        </div>
    );
};

export default AuthenticationRouter;
