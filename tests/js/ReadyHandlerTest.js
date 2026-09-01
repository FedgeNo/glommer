import { TestCase } from './TestCase.js';
import { ReadyHandler } from '../../scripts/Runtime.js';

export default {
    suite: 'ReadyHandler',
    tests: {
        async 'a late initializer waits for its compounded module to finish evaluating'() {
            let answer = null;

            ReadyHandler.add(() => {
                answer = laterDeclaration;
            });

            const laterDeclaration = 'available';

            TestCase.assertNull(answer, 'the initializer ran inside module evaluation');
            await Promise.resolve();
            TestCase.assertEquals('available', answer);
        },
    }
};
