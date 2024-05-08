import { useCallback, useEffect, useState } from '@wordpress/element';
import { debounce } from 'lodash';
import { __ } from '@wordpress/i18n';
import { CURRENCY } from '@woocommerce/settings';

import '../styles/delivery-price.scss';

const RulesComponent = ({ rules, onRulesUpdate }) => {
    const changeTo = (e, index) => {
        rules[index].to = parseInt(e.target.value);
        rules.forEach((e, i) => {
            if (i === 0 || e.to === null) {
                return;
            }
            if (e.to <= rules[i - 1].to) {
                rules[i].to = rules[i - 1].to + 1;
            }
        });
        onRulesUpdate([...rules]);
    };

    const changeToDebounced = useCallback(debounce(changeTo, 4000), [rules]);
    const changeType = (e, index) => {
        rules[index].type = e.target.value;
        onRulesUpdate([...rules]);
    };

    const changeValue = (e, index) => {
        rules[index].value = e.target.value;
        onRulesUpdate([...rules]);
    };

    const changeValueDebounced = useCallback(debounce(changeValue, 4000), [rules]);

    const removeRule = (index) => {
        if (index === (rules.length - 1)) {
            rules[index - 1].to = null;
        }
        onRulesUpdate(rules.filter((e, i) => i !== index));
    };

    return rules.map((rule, index) => (<div
      key={rule.to + rule.value + rule.type + index}>{__('Order price',
      'cdekdelivery')} {rules[index - 1] &&
      <>{__('from', 'cdekdelivery')} {rules[index -
      1].to}{CURRENCY.symbol}</>} {rule.to &&
      <>{__('less or equal', 'cdekdelivery')}
          <input defaultValue={rule.to}
                 min={rules[index - 1] ? rules[index - 1].to + 1 : 0}
                 type="number"
                 onBlur={(e) => changeTo(e, index)}
                 onInput={(e) => changeToDebounced(e,
                   index)} />{CURRENCY.symbol}</>} {rules.length === 1 &&
      <>{__('any', 'cdekdelivery')}</>}, {__('delivery price',
      'cdekdelivery')}
        <select onChange={(e) => changeType(e, index)}
                className="cdek-selector" defaultValue={rule.type}>
            <option value="free">
                {__('free', 'cdekdelivery')}
            </option>
            <option value="percentage">
                {__('percentage', 'cdekdelivery')}
            </option>
            <option value="fixed">
                {__('fixed on', 'cdekdelivery')}
            </option>
            <option value="amount">
                {__('amount on', 'cdekdelivery')}
            </option>
        </select>
        {rule.type !== 'free' && <input defaultValue={rule.value} type="number"
                                        min={rule.type === 'amount' ? null : 0}
                                        onBlur={(e) => changeValue(e, index)}
                                        onInput={(e) => changeValueDebounced(e,
                                          index)} />}
        {rule.type === 'percentage' && <>%</>}
        {(rule.type === 'amount' || rule.type === 'fixed') &&
          <>{CURRENCY.symbol}</>}
        {index !== 0 && <span className="button button-link-delete"
                              onClick={() => removeRule(index)}>-</span>}
    </div>));
};

export const DeliveryPrice = ({ input }) => {
    const [doorRules, setDoorRules] = useState([]);
    const [officeRules, setOfficeRules] = useState([]);

    const debouncedSetRules = useCallback(debounce((doorRules, officeRules) => {
        input.val(JSON.stringify({
            door: doorRules, office: officeRules,
        }));
    }, 300), []);

    useEffect(() => {
        debouncedSetRules(doorRules, officeRules);
    }, [doorRules, officeRules]);

    useEffect(() => {
        try {
            const rules = JSON.parse(input.val());
            setDoorRules(rules.door);
            setOfficeRules(rules.office);
        } catch (e) {
            setDoorRules([
                {
                    to: null, type: 'percentage', value: 100,
                }]);
            setOfficeRules([
                {
                    to: null, type: 'percentage', value: 100,
                }]);
        }
    }, []);

    const addDoorRule = () => {
        doorRules[doorRules.length - 1].to = (doorRules[doorRules.length - 2] ||
          { to: 0 }).to + 1;
        setDoorRules([
            ...doorRules, {
                to: null, type: 'percentage', value: 100,
            }]);
    };

    const addOfficeRule = () => {
        officeRules[officeRules.length -
        1].to = (officeRules[officeRules.length - 2] || { to: 0 }).to + 1;
        setOfficeRules([
            ...officeRules, {
                to: null, type: 'percentage', value: 100,
            }]);
    };

    return (<>
        <div className="cdek-delivery-rules">
            <div className="cdek-header"><h4>{__(
              'Rules for delivery by courier', 'cdekdelivery')}</h4>
                <span className="button"
                      onClick={addDoorRule}>+</span>
            </div>
            <RulesComponent rules={doorRules} onRulesUpdate={setDoorRules} />
            <div className="cdek-header"><h4>{__(
              'Rules for delivery to pick-up', 'cdekdelivery')}</h4>
                <span className="button"
                      onClick={addOfficeRule}>+</span>
            </div>
            <RulesComponent rules={officeRules}
                            onRulesUpdate={setOfficeRules} />
        </div>
    </>);
};
