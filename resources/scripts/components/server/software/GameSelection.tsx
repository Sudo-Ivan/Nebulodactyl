import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Button } from '@/components/ui/button';
import DescriptionText from './DescriptionText';
import type { Nest } from './types';

const hidden_nest_prefix = '!';

interface Props {
    nests: Nest[];
    onSelectNest: (nest: Nest) => void;
    onBack: () => void;
}

const GameSelection = ({ nests, onSelectNest, onBack }: Props) => (
    <TitledGreyBox title='Select Category'>
        <div className='space-y-4'>
            <p className='text-sm text-cream-500'>Choose the type of game or software you want to run</p>

            <div className='grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 sm:gap-4'>
                {nests?.map((nest) =>
                    nest?.attributes?.name?.includes(hidden_nest_prefix) ? null : (
                        <button
                            type='button'
                            key={nest?.attributes?.uuid}
                            onClick={() => onSelectNest(nest)}
                            className='p-4 sm:p-5 bg-cream-50/3 border border-cream-50/7 rounded-lg hover:border-cream-50/13 transition-all text-left active:bg-cream-50/7 touch-manipulation'
                        >
                            <h3 className='font-semibold text-cream-100 mb-2 text-base sm:text-lg'>
                                {nest?.attributes?.name}
                            </h3>
                            <DescriptionText
                                description={nest?.attributes?.description || ''}
                                id={`nest-${nest?.attributes?.uuid}`}
                            />
                        </button>
                    ),
                )}
            </div>

            <div className='flex justify-center pt-4'>
                <Button variant='secondary' onClick={onBack} className='w-full sm:w-auto'>
                    Back to Overview
                </Button>
            </div>
        </div>
    </TitledGreyBox>
);

export default GameSelection;
