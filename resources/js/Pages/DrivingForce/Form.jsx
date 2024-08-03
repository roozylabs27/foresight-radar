import { Form, Input, Select } from "antd";
import TextArea from "antd/es/input/TextArea";
import React, {
    useEffect,
    useImperativeHandle,
    useState,
    forwardRef,
} from "react";

const FormDrivingForce = forwardRef(
    ({ initialValues, isEditMode, dimensions, status, onFinish }, ref) => {
        const [form] = Form.useForm();

        useEffect(() => {
            if (isEditMode) {
                form.setFieldsValue(initialValues);
            } else {
                form.resetFields();
            }
        }, [isEditMode, initialValues, form]);

        // Expose form submit function to parent component
        useImperativeHandle(ref, () => ({
            submit: () => {
                form.submit();
            },
        }));

        return (
            <Form
                form={form}
                name="basic"
                initialValues={initialValues}
                layout="vertical"
                onFinish={onFinish}
            >
                <Form.Item
                    name="keyword"
                    label="Keyword"
                    rules={[
                        {
                            required: true,
                            message: "Please input the keyword!",
                        },
                    ]}
                >
                    <Input
                        placeholder="Enter keyword"
                        autoFocus={isEditMode ? false : true}
                    />
                </Form.Item>
                <Form.Item
                    name="description"
                    label="Description"
                    rules={[
                        {
                            required: true,
                            message: "Please input the description!",
                        },
                    ]}
                >
                    <Input placeholder="Enter description" />
                </Form.Item>
                <Form.Item
                    name="dimension"
                    label="Dimension"
                    rules={[
                        {
                            required: true,
                            message: "Please select the dimension!",
                        },
                    ]}
                >
                    <Select
                        style={{ width: "100%" }}
                        placeholder="Select a dimension"
                        options={dimensions}
                    />
                </Form.Item>
                {isEditMode && (
                    <>
                        <Form.Item
                            name="status"
                            label="Status"
                            rules={[
                                {
                                    required: true,
                                    message: "Please select the status!",
                                },
                            ]}
                        >
                            <Select
                                style={{ width: "100%" }}
                                placeholder="Select a Status"
                                options={status}
                            />
                        </Form.Item>
                        <Form.Item name="remark" label="Remark">
                            <TextArea
                                placeholder="Enter a remark"
                                autoSize={{ minRows: 3, maxRows: 5 }}
                            />
                        </Form.Item>
                    </>
                )}
            </Form>
        );
    }
);

export default FormDrivingForce;
