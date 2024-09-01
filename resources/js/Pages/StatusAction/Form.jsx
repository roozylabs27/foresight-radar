import { Col, Form, Input, Row, Select, Timeline } from "antd";
import React, { useEffect, useImperativeHandle, forwardRef } from "react";

const FormStatusAction = forwardRef(
    ({ initialValues, errors, onFinish, loading, status_actions }, ref) => {
        const { TextArea } = Input;
        const [form] = Form.useForm();
        useEffect(() => {
            form.setFieldsValue(initialValues);
        }, [initialValues, form]);

        // Expose form submit function to parent component
        useImperativeHandle(ref, () => ({
            submit: () => {
                form.submit();
            },
            reset: () => {
                form.setFieldsValue(initialValues);
            },
        }));

        return (
            <Row gutter={16}>
                <Col span={12} style={{ marginTop: "30px" }}>
                    <div
                        style={{
                            height: "500px",
                            overflowY: "scroll",
                            paddingRight: "25px",
                            paddingTop: "10px",
                        }}
                    >
                        <Timeline
                            pending="Recording..."
                            reverse={true}
                            items={initialValues.reasons}
                        />
                    </div>
                </Col>
                <Col span={12}>
                    <Form
                        form={form}
                        name="basic"
                        disabled={loading}
                        initialValues={initialValues}
                        layout="vertical"
                        onFinish={onFinish}
                    >
                        <Row gutter={12}>
                            <Col span={12}>
                                <Form.Item
                                    name="keyword"
                                    label="Keyword"
                                    validateStatus={
                                        errors?.keyword ? "error" : ""
                                    }
                                    help={errors?.keyword}
                                    validateTrigger="onBlur"
                                    rules={[
                                        {
                                            required: true,
                                            message:
                                                "Please input the keyword!",
                                        },
                                    ]}
                                >
                                    <Input
                                        placeholder="Enter keyword"
                                        disabled={true}
                                    />
                                </Form.Item>
                            </Col>
                            <Col span={12}>
                                <Form.Item
                                    name="description"
                                    label="Description"
                                    validateStatus={
                                        errors?.description ? "error" : ""
                                    }
                                    help={errors?.description}
                                    validateTrigger="onBlur"
                                    rules={[
                                        {
                                            required: true,
                                            message:
                                                "Please input the description!",
                                        },
                                    ]}
                                >
                                    <TextArea
                                        placeholder="Enter a reason"
                                        autoSize={{
                                            minRows: 3,
                                            maxRows: 5,
                                        }}
                                        disabled
                                    />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Row gutter={12}>
                            <Col span={12}>
                                <Form.Item
                                    name="dimension"
                                    label="Dimension"
                                    validateTrigger="onBlur"
                                    rules={[
                                        {
                                            required: true,
                                            message:
                                                "Please select the dimension!",
                                        },
                                    ]}
                                >
                                    <Select
                                        style={{ width: "100%" }}
                                        placeholder="Select a dimension"
                                        disabled={true}
                                    />
                                </Form.Item>
                            </Col>
                            <Col span={12}>
                                <Form.Item
                                    name="time_horizon"
                                    label="Time Horizon"
                                    validateTrigger="onBlur"
                                    rules={[
                                        {
                                            required: true,
                                            message:
                                                "Please select the time horizon!",
                                        },
                                    ]}
                                >
                                    <Select
                                        style={{ width: "100%" }}
                                        placeholder="Select a time horizon"
                                        disabled={true}
                                    />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Row gutter={12}>
                            <Col span={12}>
                                <Form.Item
                                    name="uncertainty_analysis"
                                    label="Uncertainty Analysis"
                                    rules={[
                                        {
                                            required: true,
                                            message:
                                                "Please select the time horizon!",
                                        },
                                    ]}
                                >
                                    <Input disabled={true} />
                                </Form.Item>
                            </Col>
                            <Col span={12}>
                                <Form.Item
                                    name="impact_analysis"
                                    label="Impact Analysis"
                                    rules={[
                                        {
                                            required: true,
                                            message:
                                                "Please select the time horizon!",
                                        },
                                    ]}
                                >
                                    <Input disabled={true} />
                                </Form.Item>
                            </Col>
                        </Row>

                        <Row gutter={12}>
                            <Col span={12}>
                                {" "}
                                <Form.Item
                                    name="priority"
                                    label="Priority"
                                    validateTrigger="onBlur"
                                    rules={[
                                        {
                                            required: true,
                                            message:
                                                "Please select the priority!",
                                        },
                                    ]}
                                >
                                    <Select
                                        style={{ width: "100%" }}
                                        placeholder="Select a priority"
                                        disabled={true}
                                    />
                                </Form.Item>
                            </Col>
                            <Col span={12}>
                                <Form.Item
                                    name="status_action_id"
                                    label="Status Action"
                                    validateTrigger="onBlur"
                                    rules={[
                                        {
                                            required: true,
                                            message:
                                                "Please select the status action !",
                                        },
                                    ]}
                                >
                                    <Select
                                        style={{ width: "100%" }}
                                        placeholder="Select a status action"
                                        options={status_actions}
                                    />
                                </Form.Item>
                            </Col>
                        </Row>

                        <Row>
                            <Col span={24}>
                                <Form.Item
                                    name="reason"
                                    label="Reason"
                                    validateTrigger="onBlur"
                                    rules={[
                                        {
                                            required: true,
                                            message: "Please input the reason!",
                                        },
                                    ]}
                                >
                                    <TextArea
                                        placeholder="Enter a reason"
                                        autoSize={{ minRows: 3, maxRows: 5 }}
                                    />
                                </Form.Item>
                            </Col>
                        </Row>
                    </Form>
                </Col>
            </Row>
        );
    }
);

export default FormStatusAction;
