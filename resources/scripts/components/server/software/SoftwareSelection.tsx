import Spinner from '@/components/elements/Spinner';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Button } from '@/components/ui/button';
import DescriptionText from './DescriptionText';
import type { Egg, Nest } from './types';

interface Props {
    selectedNest: Nest;
    isLoading: boolean;
    selectedEggUuid: string | undefined;
    onSelectEgg: (egg: Egg) => void;
    onBack: () => void;
    onCancel: () => void;
}

const SoftwareSelection = ({ selectedNest, isLoading, selectedEggUuid, onSelectEgg, onBack, onCancel }: Props) => (
    <TitledGreyBox title={`Select Software - ${selectedNest?.attributes.name}`}>
        <div className='space-y-4'>
            <p className='text-sm text-cream-500'>Choose the specific software version for your server</p>

            {isLoading ? (
                <div className='flex items-center justify-center py-16'>
                    <div className='flex flex-col items-center text-center'>
                        <Spinner size='large' />
                        <p className='text-cream-500 mt-4'>Loading software options...</p>
                    </div>
                </div>
            ) : (
                <div className='grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4'>
                    {selectedNest?.attributes?.relationships?.eggs?.data?.map((egg) => (
                        <button
                            type='button'
                            key={egg.attributes.uuid}
                            onClick={() => onSelectEgg(egg)}
                            disabled={isLoading}
                            className='p-4 bg-cream-50/3 border border-cream-50/7 rounded-lg hover:border-cream-50/13 transition-all text-left touch-manipulation disabled:opacity-50 disabled:cursor-not-allowed'
                        >
                            <div className='flex items-center gap-2 mb-2'>
                                {isLoading && selectedEggUuid === egg?.attributes?.uuid && <Spinner size='small' />}
                                <h3 className='font-semibold text-cream-100 text-sm sm:text-base'>
                                    {egg?.attributes?.name}
                                </h3>
                            </div>
                            <DescriptionText
                                description={egg?.attributes?.description || ''}
                                id={`egg-${egg?.attributes?.uuid}`}
                            />
                        </button>
                    ))}
                </div>
            )}

            <div className='flex flex-col sm:flex-row justify-center gap-3 pt-4'>
                <Button variant='secondary' onClick={onBack} className='w-full sm:w-auto'>
                    Back to Games
                </Button>
                <Button variant='secondary' onClick={onCancel} className='w-full sm:w-auto'>
                    Cancel
                </Button>
            </div>
        </div>
    </TitledGreyBox>
);

export default SoftwareSelection;
