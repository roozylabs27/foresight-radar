import { Button, Form, Modal } from "antd";

export default function Dialog({
    title,
    open,
    onCancel,
    isEditMode,
    onOk,
    btnText = isEditMode ? "Save Changes": "Submit",
    loading,
    children,
    width = 520
}) {
    return (
        <>
            <Modal
                title={title}
                open={open}
                centered
                onCancel={onCancel}
                width={width}
                footer={[
                    <Button key="back" disabled={loading} onClick={onCancel}>
                        Cancel
                    </Button>,
                    <Button
                        key="submit"
                        type="primary"
                        loading={loading}
                        disabled={loading}
                        onClick={onOk}
                    >
                        {btnText}
                    </Button>,
                ]}
            >
                {children}
            </Modal>
        </>
    );
}
