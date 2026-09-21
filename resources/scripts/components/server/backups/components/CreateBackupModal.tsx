import { Form, Formik, Field as FormikField, type FormikHelpers, useFormikContext } from 'formik';
import { useEffect } from 'react';
import { boolean, object, string } from 'yup';
import Can from '@/components/elements/Can';
import Field from '@/components/elements/Field';
import FormikFieldWrapper from '@/components/elements/FormikFieldWrapper';
import FormikSwitchV2 from '@/components/elements/FormikSwitchV2';
import { Textarea } from '@/components/elements/Input';
import Modal, { type RequiredModalProps } from '@/components/elements/Modal';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Button } from '@/components/ui/button';
import useFlash from '@/plugins/useFlash';

interface BackupValues {
    name: string;
    ignored: string;
    isLocked: boolean;
}

interface CreateBackupModalProps extends RequiredModalProps {
    onSubmit: (values: BackupValues, helpers: FormikHelpers<BackupValues>) => Promise<void>;
}

const ModalContent = ({ ...props }: RequiredModalProps) => {
    const { isSubmitting } = useFormikContext<BackupValues>();

    return (
        <Modal {...props} showSpinnerOverlay={isSubmitting} title='Create server backup'>
            <Form>
                <FlashMessageRender byKey={'backups:create'} />
                <Field
                    name={'name'}
                    label={'Backup name'}
                    description={'If provided, the name that should be used to reference this backup.'}
                />
                <div className={`mt-6 flex flex-col`}>
                    <FormikFieldWrapper
                        className='flex flex-col gap-2'
                        name={'ignored'}
                        label={'Ignored Files & Directories'}
                        description={`
              Enter the files or folders to ignore while generating this backup. Leave blank to use
              the contents of the .pteroignore file in the root of the server directory if present.
              Wildcard matching of files and folders is supported in addition to negating a rule by
              prefixing the path with an exclamation point.
            `}
                    >
                        <FormikField
                            as={Textarea}
                            className='px-4 py-2 rounded-lg outline-hidden bg-cream-50/9 text-sm'
                            name={'ignored'}
                            rows={6}
                        />
                    </FormikFieldWrapper>
                </div>
                <Can action={'backup.delete'}>
                    <div className={`my-6`}>
                        <FormikSwitchV2
                            name={'isLocked'}
                            label={'Locked'}
                            description={'Prevents this backup from being deleted until explicitly unlocked.'}
                        />
                    </div>
                </Can>
                <div className={`flex justify-end mb-6`}>
                    <Button variant='attention' type={'submit'} disabled={isSubmitting}>
                        {isSubmitting && <Spinner size='small' />}
                        {isSubmitting ? 'Creating backup...' : 'Start backup'}
                    </Button>
                </div>
            </Form>
        </Modal>
    );
};

const CreateBackupModal = ({ visible, onDismissed, onSubmit }: CreateBackupModalProps) => {
    const { clearFlashes } = useFlash();

    useEffect(() => {
        clearFlashes('backups:create');
    }, [clearFlashes]);

    return (
        <Formik
            onSubmit={onSubmit}
            initialValues={{ name: '', ignored: '', isLocked: false }}
            validationSchema={object().shape({
                name: string().max(191),
                ignored: string(),
                isLocked: boolean(),
            })}
        >
            <ModalContent visible={visible} onDismissed={onDismissed} />
        </Formik>
    );
};

export default CreateBackupModal;
