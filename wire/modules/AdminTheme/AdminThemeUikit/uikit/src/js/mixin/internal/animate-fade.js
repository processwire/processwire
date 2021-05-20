import {getRows} from '../../core/margin';
import {addClass, children, css, hasClass, height, isInView, once, Promise, removeClass, sortBy, toNumber, Transition} from 'uikit-util';

const clsLeave = 'uk-transition-leave';
const clsEnter = 'uk-transition-enter';

export default function fade(action, target, duration, stagger = 0) {

    const index = transitionIndex(target, true);
    const propsIn = {opacity: 1};
    const propsOut = {opacity: 0};

    const wrapIndexFn = fn =>
        () => index === transitionIndex(target) ? fn() : Promise.reject();

    const leaveFn = wrapIndexFn(() => {

        addClass(target, clsLeave);

        return Promise.all(getTransitionNodes(target).map((child, i) =>
            new Promise(resolve =>
                setTimeout(() => Transition.start(child, propsOut, duration / 2, 'ease').then(resolve), i * stagger)
            )
        )).then(() => removeClass(target, clsLeave));

    });

    const enterFn = wrapIndexFn(() => {

        const oldHeight = height(target);

        addClass(target, clsEnter);
        action();

        css(children(target), {opacity: 0});

        // Ensure UIkit updates have propagated
        return new Promise(resolve =>
            requestAnimationFrame(() => {

                const nodes = children(target);
                const newHeight = height(target);

                // Ensure Grid cells do not stretch when height is applied
                css(target, 'alignContent', 'flex-start');
                height(target, oldHeight);

                const transitionNodes = getTransitionNodes(target);
                css(nodes, propsOut);

                const transitions = transitionNodes.map((child, i) =>
                    new Promise(resolve =>
                        setTimeout(() => Transition.start(child, propsIn, duration / 2, 'ease').then(resolve), i * stagger)
                    )
                );

                if (oldHeight !== newHeight) {
                    transitions.push(Transition.start(target, {height: newHeight}, duration / 2 + transitionNodes.length * stagger, 'ease'));
                }

                Promise.all(transitions).then(() => {
                    removeClass(target, clsEnter);
                    if (index === transitionIndex(target)) {
                        css(target, {height: '', alignContent: ''});
                        css(nodes, {opacity: ''});
                        delete target.dataset.transition;
                    }
                    resolve();
                });
            })
        );
    });

    return hasClass(target, clsLeave)
        ? waitTransitionend(target).then(enterFn)
        : hasClass(target, clsEnter)
            ? waitTransitionend(target).then(leaveFn).then(enterFn)
            : leaveFn().then(enterFn);
}

function transitionIndex(target, next) {
    if (next) {
        target.dataset.transition = 1 + transitionIndex(target);
    }

    return toNumber(target.dataset.transition) || 0;
}

function waitTransitionend(target) {
    return Promise.all(children(target).filter(Transition.inProgress).map(el =>
        new Promise(resolve => once(el, 'transitionend transitioncanceled', resolve))
    ));
}

function getTransitionNodes(target) {
    return getRows(children(target)).reduce((nodes, row) => nodes.concat(sortBy(row.filter(el => isInView(el)), 'offsetLeft')), []);
}
