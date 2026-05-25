import { getAvailableHeightBelowAnchor } from './useTableViewportCapacity';

describe( 'getAvailableHeightBelowAnchor', () => {
	it( 'returns remaining client height below the anchor inside main', () => {
		const main = {
			clientHeight: 800,
			scrollTop: 100,
			getBoundingClientRect: () => ( { top: 50 } ),
		};
		const anchor = {
			getBoundingClientRect: () => ( { top: 250 } ),
		};

		Object.defineProperty( window, 'getComputedStyle', {
			value: () => ( { paddingBottom: '0px' } ),
		} );

		// anchorTopInMain = 250 - 50 + 100 = 300; 800 - 300 - 16 = 484
		expect( getAvailableHeightBelowAnchor( main, anchor ) ).toBe( 484 );
	} );
} );
