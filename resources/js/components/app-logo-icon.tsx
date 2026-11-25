import { cn } from '@/lib/utils';
import { HTMLAttributes } from 'react';

const logoMask = "url('/courier-logo-mask.png')";

export default function AppLogoIcon({ className, style, ...props }: HTMLAttributes<HTMLSpanElement>) {
    return (
        <span
            {...props}
            aria-hidden="true"
            className={cn('block bg-current', className)}
            style={{
                maskImage: logoMask,
                maskMode: 'alpha',
                maskPosition: 'center',
                maskRepeat: 'no-repeat',
                maskSize: 'contain',
                WebkitMaskImage: logoMask,
                WebkitMaskPosition: 'center',
                WebkitMaskRepeat: 'no-repeat',
                WebkitMaskSize: 'contain',
                ...style,
            }}
        />
    );
}
